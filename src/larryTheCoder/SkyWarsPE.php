<?php

// LANGUAGE CHECK SUCCESS

/**
 * TO-DO list for 1.9_Alpha
 * <X> Player kill message on Level
 * < > Add 1/2 arena loading
 * < > Add Sqlite & YAML Database
 */

namespace larryTheCoder;

use larryTheCoder\arena\Arena;
use larryTheCoder\commands\SkyWarsCommand;
use larryTheCoder\utils\ConfigManager;
use larryTheCoder\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

#

/**
 * The main class for SkyWars plugin
 * Insider program for HyrulePE.
 *
 * @package larryTheCoder
 */
class SkyWarsPE extends PluginBase implements Listener {

	/** @var SkyWarsPE */
	public static $instance;
	/** @var Config[] */
	public $arenas = [];
	/** @var Config */
	public $cfg;
	/** @var Config */
	public $msg;
	/** @var array */
	public $ban = [];
	/** @var Arena[] */
	public $ins = [];
	/** @var SkyWarsCommand */
	public $cmd;
	public $selectors = [];

	/** @var Arena[] */
	public $inv = [];
	public $setters = [];
	public $economy;
	public $shops = null;
	public $listener = null;
	public $mode = 0;

	public static function getInstance() {
		return self::$instance;
	}

	public function onEnable() {
		$this->initConfig();

		if (!$this->cfg->get("use_economy", false)) {
			$this->registerEconomy();
		}

		$this->checkArenas();
		$this->getServer()->getPluginManager()->registerEvents(/* SkyWarslistener( */
			$this/* ) */, $this);

		self::$instance = $this;
		$this->cmd = new SkyWarsCommand($this);

		$this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::GREEN . "SkyWarsForPE has been enabled");
	}

	public function initConfig() {
		if (!file_exists($this->getDataFolder())) {
			@mkdir($this->getDataFolder());
		}

		if (!is_file($this->getDataFolder() . "config.yml")) {
			$this->saveResource("config.yml");
		}
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if (!file_exists($this->getDataFolder() . "/arenas/worlds/")) {
			@mkdir($this->getDataFolder() . "/arenas/worlds/");
		}
		if (!file_exists($this->getDataFolder() . "language/")) {
			@mkdir($this->getDataFolder() . "language/");
		}
		if (!file_exists($this->getDataFolder() . "arenas/")) {
			@mkdir($this->getDataFolder() . "arenas/");
			$this->saveResource("arenas/default.yml");
		}
		if (!file_exists($this->getDataFolder() . "players/")) {
			@mkdir($this->getDataFolder() . "players/");
		}
		if (!is_file($this->getDataFolder() . "language/English.yml")) {
			$this->saveResource("language/English.yml");
		} else {
			$this->msg = new Config($this->getDataFolder() . "language/{$this->cfg->get('language')}.yml", Config::YAML);
			$this->getServer()->getLogger()->info("Selected language {$this->cfg->get('language')}");
		}
	}

	public function registerEconomy() {
		$economy = ["EconomyAPI", "PocketMoney", "MassiveEconomy", "GoldStd"];
		foreach ($economy as $plugin) {
			$ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
			if ($ins instanceof Plugin && $ins->isEnabled()) {
				$this->economy = $ins;
				$this->getServer()->getLogger()->info($this->getPrefix() . "§bSelected economy plugin :§c $plugin");
				return;
			}
		}
		$this->economy = null;
	}

	public function getPrefix() {
		return \str_replace("&", "§", $this->cfg->get('Prefix'));
	}

	public function checkArenas() {
		$this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::AQUA . "checking arena files...");
		foreach (glob($this->getDataFolder() . "arenas/*.yml") as $file) {
			$arena = new Config($file, Config::YAML);
			if (strtolower($arena->get("enabled")) === "false") {
				$this->arenas[basename($file, ".yml")] = $arena->getAll();
				$this->arenas[basename($file, ".yml")]['enable'] = false;
				$fname = basename($file);
				$this->getServer()->getLogger()->info($this->getPrefix() . "§a$fname §7§l-§r§c is disabled");
			} else {
				if (Utils::checkFile($arena) === true) {
					$fname = basename($file);
					$this->setArenasData($arena, basename($file, ".yml"));
					$this->getServer()->getLogger()->info($this->getPrefix() . "§c$fname §7§l-§r§a checking sucessful");
				} else {
					$this->arenas[basename($file, ".yml")] = $arena->getAll();
					$this->arenas[basename($file, ".yml")]['enable'] = false;
					//$this->setArenasData($arena, basename($file, ".yml"), false);
					$fname = basename($file, ".yml");
					$this->getServer()->getLogger()->error("Arena $fname is not set properly");
				}
			}
		}
	}

	public function setArenasData(Config $arena, $name) {
		$this->arenas[$name] = $arena->getAll();
		$this->arenas[$name]['enable'] = true;
		$game = new Arena($name, $this);
		$this->ins[$name] = $game;
		$this->getServer()->getPluginManager()->registerEvents($game, $this);
	}

	public function onDisable() {
		Utils::unLoadGame();
		$this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::RED . 'SkyWarsForPE has disabled');
	}

	public function getPlayerArena(Player $p) {
		foreach ($this->ins as $arena) {
			$players = array_merge($arena->players, $arena->spec);
			if (isset($players[strtolower($p->getName())])) {
				return $arena;
			}
		}
		return false;
	}

	public function arenaExist($name) {
		if (isset($this->arenas[$name])) {
			return true;
		}
		return false;
	}

	public function loadInvs() {
		foreach ($this->getServer()->getOnlinePlayers() as $p) {
			if (isset($this->inv[strtolower($p->getName())])) {
				foreach ($this->inv as $slot => $i) {
					list($id, $dmg, $count) = explode(":", $i);
					$item = Item::get($id, $dmg, $count);
					$p->getInventory()->setItem($slot, $item);
					unset($this->inv[strtolower($p->getName())]);
				}
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		return $this->cmd->onCommand($sender, $command, $args);
	}

	public function setLobby(Player $p) {
		$location = $p->getLocation();
		$this->cfg->setNested("lobby", ["spawn_x" => \round($location->getFloorX(), 0), "spawn_y" => \round($location->getFloorY(), 0), "spawn_z" => \round($location->getFloorZ(), 0), "world" => $p->getLevel()->getName()]);
		$this->cfg->save();
		$p->sendMessage($this->getPrefix() . $this->getMsg("set_main_lobby"));
		return true;
	}

	public function getMsg($key) {
		$msg = str_replace("&", "§", $this->msg->get($key));
		return $msg;
	}

	/**
	 * @param PlayerQuitEvent $e
	 * @priority MONITOR
	 */
	public function onQuit(PlayerQuitEvent $e) {
		$p = $e->getPlayer();
		$this->unsetPlayers($p);
	}

	public function unsetPlayers(Player $p) {
		if (isset($this->selectors[strtolower($p->getName())])) {
			unset($this->selectors[strtolower($p->getName())]);
		}
		if (isset($this->setters[strtolower($p->getName())])) {
			$this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
			if ($this->isArenaSet($this->setters[strtolower($p->getName())]['arena'])) {
				$a = new Arena($this->setters[strtolower($p->getName())]['arena'], $this);
				$a->setup = false;
			}
			unset($this->setters[strtolower($p->getName())]);
		}
	}

	public function reloadArena($name) {
		$arena = new Config($this->getDataFolder() . "arenas/$name.yml");
		if (isset($this->ins[$name])) {
			$this->ins[$name]->setup = false;
		}
		if (!Utils::checkFile($arena) || $arena->get('enabled') === "false") {
			$this->arenas[$name] = $arena->getAll();
			$this->arenas[$name]['enable'] = 'false';
			return;
		}
		if ($this->arenas[$name]['enable'] === 'false') {
			$this->setArenasData($arena, $name);
			return;
		}
		$this->arenas[$name] = $arena->getAll();
		$this->arenas[$name]['enable'] = 'true';
		$this->ins[$name]->data = $this->arenas[$name];
	}

	public function isArenaSet($name) {
		if (isset($this->ins[$name])) {
			return true;
		}
		return false;
	}

	/**
	 * @param PlayerKickEvent $e
	 * @priority MONITOR
	 */
	public function onKick(PlayerKickEvent $e) {
		$p = $e->getPlayer();
		$this->unsetPlayers($p);
	}

	/**
	 * @param PlayerLoginEvent $e
	 * @priority MONITOR
	 */
	public function onPlayerLogin(PlayerLoginEvent $e) {
		$p = $e->getPlayer();
		# Config configuration
		if (!file_exists($this->getDataFolder() . "players/{$p->getName()}.yml")) {
			$conf = new Config($this->getDataFolder() . "players/{$p->getName()}.yml", Config::YAML);
			$conf->set("ban", false);
			$conf->set("points", 0);
			$conf->set("kills", 0);
			$conf->set("deaths", 0);
			$conf->set("win", 0);
			$conf->set("lose", 0);
			$conf->save();
		}
	}

	/**
	 * @param BlockBreakEvent $e
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $e) {
		$p = $e->getPlayer();
		if (isset($this->setters[strtolower($p->getName())]['arena']) && isset($this->setters[strtolower($p->getName())]['type'])) {
			$e->setCancelled(true);
			$b = $e->getBlock();
			$arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
			if ($this->setters[strtolower($p->getName())]['type'] == "setjoinsign") {
				$arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
				$p->sendMessage($this->getPrefix() . $this->getMsg('joinsign'));
				unset($this->setters[strtolower($p->getName())]['type']);
				return;
			}
			if ($this->setters[strtolower($p->getName())]['type'] == "setspecspawn") {
				$arena->setSpecSpawn($b->x, $b->y, $b->z);
				$p->sendMessage($this->getPrefix() . $this->getMsg('spectatorspawn'));
				unset($this->setters[strtolower($p->getName())]['type']);
				return;
			}
			if ($this->setters[strtolower($p->getName())]['type'] == "spawnpos") {
				if ($this->mode >= 1 && $this->mode <= $arena->arena->getNested('arena.max_players')) {
					$arena->arena->setNested("arena.spawn_positions.spawn$this->mode", [$b->getX(), $b->getY() + 1, $b->getZ()]);
					$p->sendMessage(str_replace("%1", $this->mode, $this->getPrefix() . $this->getMsg('arena_setup_spawnpos')));
					$this->mode++;
				} else if ($this->mode == $arena->arena->getNested('arena.max_players') + 1) {
					$p->sendMessage($this->getPrefix() . $this->getMsg("spawnpos"));
					$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
					$p->teleport($spawn, 0, 0);
					$this->mode = 0;
					unset($this->setters[strtolower($p->getName())]['type']);
				}
				$arena->arena->save();
				return;
			}
		}
	}

	/**
	 * @param PlayerChatEvent $e
	 * @priority HIGH
	 */
	public function onChat(PlayerChatEvent $e) {
		$p = $e->getPlayer();
		$msg = strtolower(trim($e->getMessage()));
		if (isset($this->setters[strtolower($p->getName())]['arena'])) {
			$e->setCancelled(true);
			$arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
			switch ($msg) {
				case 'joinsign':
					$this->setters[strtolower($p->getName())]['type'] = 'setjoinsign';
					$p->sendMessage($this->getPrefix() . $this->getMsg('break_sign'));
					return;
				case 'spectatorspawn':
					$this->setters[strtolower($p->getName())]['type'] = 'setspecspawn';
					$p->teleport(new Position(8, 90, 8, $this->getServer()->getLevelByName($arena->arena->getNested('arena.arena_world'))));
					$p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
					return;
				case 'spawnpos':
					$this->setters[strtolower($p->getName())]['type'] = 'spawnpos';
					$p->teleport(new Position(8, 90, 8, $this->getServer()->getLevelByName($arena->arena->getNested('arena.arena_world'))));
					$this->mode = 1;
					$p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
					return;
				case 'done':
					$p->sendMessage($this->getPrefix() . $this->getMsg('disable_setup_mode'));
					$this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
					unset($this->setters[strtolower($p->getName())]);
					return;
			}
			$args = explode(' ', $msg);
			if (count($args) >= 1 && count($args) <= 3) {
				if ($args[0] === 'help') {
					$help1 = $this->getMsg('help_joinsign') . $this->getMsg('help_spawnpos') . $this->getMsg('help_spectator') . $this->getMsg('help_statusline');
					$help2 = $this->getMsg('help_allowspectator') . $this->getMsg('help_maxtime') . $this->getMsg('help_maxplayers') . $this->getMsg('help_minplayers') . $this->getMsg('help_starttime') . $this->getMsg('help_time') . $this->getMsg('help_world') . $this->getMsg('help_signupdatetime');
					$help3 = $this->getMsg('help_enable') . $this->getMsg('help_setmoney');
					$helparray = [$help1, $help2, $help3];
					if (isset($args[1])) {
						if (intval($args[1]) >= 1 && intval($args[1]) <= 3) {
							$help = "§9--- §6§lSkyWars setup help§l $args[1]/3§9 ---§r§f";
							$help .= $helparray[intval(intval($args[1]) - 1)];
							$p->sendMessage($help);
							return;
						}
						$p->sendMessage($this->getPrefix() . "§6use: §ahelp §b[page 1-3]");
						return;
					}
					$p->sendMessage("§9--- §6§lSkyWars setup help§l 1/3§9 ---§r§f" . $help1);
					return;
				}
			}
			if (count(explode(' ', $msg)) >= 3 && strpos($msg, 'statusline') !== 0) {
				$p->sendMessage($this->getPrefix() . $this->getMsg('invalid_arguments'));
				return;
			}
			if (substr($msg, 0, 10) === 'statusline') {
				if (!strlen(substr($msg, 13)) >= 1 || !intval(substr($msg, 11, 1)) >= 1 || !intval(substr($msg, 11, 1) <= 4)) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('statusline_help'));
					return;
				}
				$arena->setStatusLine($args[1], substr($msg, 13));
				$p->sendMessage($this->getPrefix() . $this->getMsg('statusline'));
				return;
				#
			} elseif (strpos($msg, "chestrefill")) {
				if (substr($msg, 7) === "help") {
					$p->sendMessage($this->getMsg("chest_refill_help"));
				} else if (substr($msg, 7) === "set") {
					if (substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
						$arena->setChestPriority(substr($msg, 7));
						$p->sendMessage($this->getPrefix() . $this->getMsg('chestset'));
						return;
					} else {
						$p->sendMessage($this->getPrefix() . $this->getMsg('chestset_help'));
					}
				} else if (substr($msg, 7) === "tick") {
					if (substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
						$arena->setChestTicks(substr($msg, 7));
						$p->sendMessage($this->getPrefix() . $this->getMsg('chesttick'));
						return;
					} else {
						$p->sendMessage($this->getPrefix() . $this->getMsg('chesttick_help'));
					}
				} else {
					$p->sendMessage($this->getMsg("chest_refill_help"));
				}
			} elseif (strpos($msg, 'enable') === 0) {
				if (substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
					$arena->setEnable(substr($msg, 7));
					$p->sendMessage($this->getPrefix() . $this->getMsg('enable'));
					return;
				}
				$p->sendMessage($this->getPrefix() . $this->getMsg('enable_help'));
				return;
			} elseif (strpos($msg, 'setmoney') === 0) {
				if (!is_numeric(substr($msg, 9))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('setmoney_help'));
				}
				$arena->setMoney(substr($msg, 9));
			} elseif (strpos($msg, 'signupdatetime') === 0) {
				if (!is_numeric(substr($msg, 15))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime_help'));
					return;
				}
				$arena->setUpdateTime(substr($msg, 15));
				$p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime'));
			} elseif (strpos($msg, 'setworld') === 0) {
				if (!is_string($msg)) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('world_help'));
				}
				$arena->setArenaWorld(substr($msg, 9));
				$p->sendMessage($this->getPrefix() . $this->getMsg('world'));
				return;
			} elseif (strpos($msg, 'allowspectator') === 0) {
				if (substr($msg, 15) === 'true' || substr($msg, 15) === 'false') {
					$arena->setSpectator(substr($msg, 15));
					$p->sendMessage($this->getPrefix() . $this->getMsg('allowspectator'));
					return;
				}
				$p->sendMessage($this->getPrefix() . $this->getMsg('allowspectator_help'));
			} elseif (strpos($msg, 'maxtime') === 0) {
				if (!is_numeric(substr($msg, 8))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('maxtime_help'));
					return;
				}
				$arena->setMaxTime(substr($msg, 8));
				$p->sendMessage($this->getPrefix() . $this->getMsg('maxtime'));
			} elseif (strpos($msg, 'allowstatus') === 0) {
				if (substr($msg, 12) === 'true' || substr($msg, 12) === 'false') {
					$arena->setStatus(substr($msg, 12));
					$p->sendMessage($this->getPrefix() . $this->getMsg('allowstatus'));
					return;
				}
				$p->sendMessage($this->getPrefix() . $this->getMsg('allowstatus_help'));
			} elseif (strpos($msg, 'maxplayers') === 0) {
				if (!is_numeric(substr($msg, 11))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('maxplayers_help'));
					return;
				}
				$arena->setMaxPlayers(substr($msg, 11));
				$p->sendMessage($this->getPrefix() . $this->getMsg('maxplayers'));
			} elseif (strpos($msg, 'minplayers') === 0) {
				if (!is_numeric(substr($msg, 11))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('minplayers_help'));
					return;
				}
				$arena->setMinPlayers(substr($msg, 11));
				$p->sendMessage($this->getPrefix() . $this->getMsg('minplayers'));
			} elseif (strpos($msg, 'starttime') === 0) {
				if (!is_numeric(substr($msg, 10))) {
					$p->sendMessage($this->getPrefix() . $this->getMsg('starttime_help'));
					return;
				}
				$arena->setStartTime(substr($msg, 10));
				$p->sendMessage($this->getPrefix() . $this->getMsg('starttime'));
			} elseif (strpos($msg, 'time') === 0) {
				if (substr($msg, 5) === 'true' || substr($msg, 5) === 'day' || substr($msg, 5) === 'night' || is_numeric(substr($msg, 5))) {
					$arena->setTime(substr($msg, 5));
					$p->sendMessage($this->getPrefix() . $this->getMsg('time'));
					return;
				}
				$p->sendMessage($this->getPrefix() . $this->getMsg('time_help'));
			} else {
				$p->sendMessage($this->getPrefix() . $this->getMsg('invalid_arguments'));
			}
		}
	}

}
