<?php

/**
 * @todo:
 *   - leave arena using an item [DONE]
 *   - start game sound system [DONE]
 *   - portal join [TO DO]
 *   - allow to use ZipArchive for world save [DONE]
 *   - invinsible player when spectate [DONE]
 */

namespace larryTheCoder\arena;

use larryTheCoder\events\PlayerJoinArenaEvent;
use larryTheCoder\events\PlayerWinArenaEvent;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\tile\Chest;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

#

/**
 * Arena : Main Arena Class
 *
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < BETA | Testing >
 *
 */
class Arena implements Listener {

	/** @var Config */
	public $data;
	/** @var SkyWarsPE */
	public $plugin;
	/** @var int */
	public $game = 0;
	/** @var bool */
	public $forceStart = false;
	/** @var Player[] */
	public $players = [];
	/** @var Player[] */
	public $spec = [];
	/** @var integer[] */
	public $claimedPedestals = [];
	/** @var string[] */
	public $winners = [];
	/** @var bool[] */
	public $dead = [];
	public $fallTime = 0;
	/** @var bool */
	public $updateLevel = false;
	/** @var Level */
	public $level;
	/** @var bool */
	public $setup = false;
	private $id;

	public function __construct($id, SkyWarsPE $plugin) {
		$this->id = $id;
		$this->plugin = $plugin;
		$this->data = $plugin->arenas[$id];
		$this->checkWorlds();
		$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
		if ($this->reload() === false) {
			$this->plugin->getLogger()->info(TextFormat::RED . 'An error occured while reloading the arena: ' . TextFormat::WHITE . $this->level->getName());
			return;
		}
		# Arena Listener
		$this->enableScheduler();
		$plugin->getServer()->getPluginManager()->registerEvents(new ArenaListener($this->plugin, $this), $plugin);
	}

	public function checkWorlds() {
		if (!$this->plugin->getServer()->isLevelGenerated($this->data['signs']['join_sign_world'])) {
			$this->plugin->getServer()->generateLevel($this->data['signs']['join_sign_world']);
		}
		if (!$this->plugin->getServer()->isLevelLoaded($this->data['signs']['join_sign_world'])) {
			$this->plugin->getServer()->loadLevel($this->data['signs']['join_sign_world']);
		}
		if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
			$this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
		}
		if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
			$this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
		}
		if (!$this->plugin->getServer()->isLevelGenerated($this->plugin->cfg->getNested('lobby.world'))) {
			$this->plugin->getServer()->generateLevel($this->plugin->cfg->getNested('lobby.world'));
		}
		if (!$this->plugin->getServer()->isLevelLoaded($this->plugin->cfg->getNested('lobby.world'))) {
			$this->plugin->getServer()->loadLevel($this->plugin->cfg->getNested('lobby.world'));
		}
		//I solved the problem :P
		$world = $this->data['arena']['arena_world'];
		if ($this->plugin->cfg->get("reset_zip", false)) {
			if (!file_exists($this->plugin->getDataFolder() . "/arenas/worlds/" . $this->data['arena']['arena_world'])) {
				Utils::copyr($this->plugin->getServer()->getDataPath() . "/worlds/" . $world, $this->plugin->getDataFolder() . "/arenas/worlds/" . $world);
			}
		} else {
			if (!is_file($this->plugin->getDataFolder() . "arenas/worlds/$world.zip")) {
				$path = realpath($this->plugin->getServer()->getDataPath() . 'worlds/' . $world);
				$zip = new \ZipArchive;
				@mkdir($this->plugin->getDataFolder() . 'arenas/worlds', 0755);
				$zip->open($this->plugin->getDataFolder() . 'arenas/worlds/' . $world . '.zip', $zip::CREATE | $zip::OVERWRITE);
				$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY);
				foreach ($files as $nu => $file) {
					if (!$file->isDir()) {
						$relativePath = $world . '/' . substr($file, strlen($path) + 1);
						$zip->addFile($file, $relativePath);
					}
				}
				$zip->close();
				$this->plugin->getServer()->loadLevel($world);
				unset($zip, $path, $files);
			}
		}
	}

	/**
	 * @return bool
	 */
	private function reload() {
		$this->fallTime = $this->data['arena']['grace_time'];
		# First thing World Reset
		if (!is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $this->data["arena"]["arena_world"] . '.zip')) {
			return false;
		}
		$levelname = $this->data["arena"]["arena_world"];
		$this->plugin->getServer()->unloadLevel($this->level);
		if ($this->plugin->cfg->get("reset_zip", false)) {
			Utils::copyr($this->plugin->getDataFolder() . "/arenas/worlds/" . $levelname, $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelname);
			if (!$this->plugin->getServer()->isLevelLoaded($levelname)) {
				$this->plugin->getServer()->loadLevel($levelname);
			}
			Utils::copyr($this->plugin->getServer()->getDataPath() . "/worlds/" . $levelname, $this->plugin->getDataFolder() . "/arenas/worlds/" . $levelname);
			return true;
		} else {
			if ($this->plugin->getServer()->isLevelLoaded($levelname)) {
				$zip = new \ZipArchive;
				$zip->open($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelname . '.zip');
				$zip->extractTo($this->plugin->getServer()->getDataPath() . 'worlds');
				$zip->close();
				unset($zip);
				$this->plugin->getServer()->loadLevel($levelname);
				$this->plugin->getServer()->getLevelByName($levelname)->setAutoSave(false);
				return true;
			}
		}
		return false;
	}

	public function enableScheduler() {
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ArenaSchedule($this), 20);
	}

	public function giveEffect($e, Player $p) {
		$effect = Effect::getEffect($e);
		if ($e === 1) {
			$effect->setAmplifier(9);
		} else if ($e === 2) {

		} else {
			$effect->setAmplifier(1);
		}
		$effect->setDuration(10);
		$effect->setVisible(false);
		$p->addEffect($effect);
	}

	public function kickPlayer($p, $reason = "") {
		/** @var Player[] $players */
		$players = array_merge($this->players, $this->spec);
		if (empty($reason)) {
			$reason = "Generic Reason";
		}
		$players[strtolower($p)]->sendMessage(str_replace("%1", $reason, $this->plugin->getMsg('kick_from_game')));
		$this->leaveArena($players[strtolower($p)], true);
	}

	public function leaveArena(Player $p, $kicked = false) {
		$sound = new EndermanTeleportSound(new Vector3());
		$sound->setComponents($p->x, $p->y, $p->z);
		if ($this->getPlayerMode($p) == 0) {
			if ($this->game === 0 or $kicked === true) {
				unset($this->players[strtolower($p->getName())]);
				$this->level->addSound($sound, [$p]);
				if ($kicked === true) {
					$this->messageArenaPlayers(str_replace("%1", $p->getName(), $this->plugin->getMsg('leave_others')));
				}
				$this->checkAlive();
			} else {
				$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('in_game'));
			}
		}
		if ($this->getPlayerMode($p) == 1) {
			unset($this->spec[strtolower($p->getName())]);
			$this->level->addSound($sound, [$p]);
			$p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
		}
		if (!$this->plugin->getServer()->isLevelGenerated($this->plugin->cfg->getNested('lobby.world'))) {
			$this->plugin->getServer()->generateLevel($this->plugin->cfg->getNested('lobby.world'));
		}
		if (!$this->plugin->getServer()->isLevelLoaded($this->plugin->cfg->getNested('lobby.world'))) {
			$this->plugin->getServer()->loadLevel($this->plugin->cfg->getNested('lobby.world'));
		}
		if ($kicked === false) {
			$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('leave'));
		}
		$this->loadInv($p);
		$p->removeAllEffects();
	}

	public function getPlayerMode(Player $p) {
		if (isset($this->players[strtolower($p->getName())])) {
			return 0;
		}
		if (isset($this->spec[strtolower($p->getName())])) {
			return 1;
		}
		return false;
	}

	public function messageArenaPlayers(string $msg) {
		$inGame = array_merge($this->players, $this->spec);
		/** @var Player $p */
		foreach ($inGame as $p) {
			$p->sendMessage($this->plugin->getPrefix() . $msg);
		}
	}

	public function checkAlive() {
		if (count($this->players) === 1) {
			foreach ($this->players as $p) {
				$this->winners[1] = $p->getName();
			}
			$this->stopGame();
		}
	}

	public function stopGame($forced = false) {
		if (!$forced) {
			$this->broadcastResult();
		}
		$this->unsetAllPlayers();
		$this->game = 0;
		$this->reload();
	}

	public function broadcastResult() {
		// TO-DO: random giveReward() to all players like brokenlens server
		$this->giveReward($this->plugin->getServer()->getPlayer($this->winners[1]));
		$this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerWinArenaEvent($this->plugin, $this->plugin->getServer()->getPlayer($this->winners[1]), $this));
		if (!isset($this->winners[1])) {
			$this->winners[1] = "---";
		}
		if (!isset($this->winners[2])) {
			$this->winners[2] = "---";
		}
		$vars = ['%1', '%2', '%3', '%4'];
		$replace = [$this->id, $this->winners[1], $this->winners[2]];
		$msg = str_replace($vars, $replace, $this->plugin->getMsg('end_game'));
		$levels = $this->plugin->getServer()->getDefaultLevel();
		foreach ($levels as $level) {
			$lvl = $this->plugin->getServer()->getLevelByName($level);
			if ($lvl instanceof Level) {
				foreach ($lvl->getPlayers() as $p) {
					$p->sendMessage($msg);
				}
			}
		}
	}

	public function giveReward(Player $p) {
		if (isset($this->data['arena']['item_reward']) && $this->data['arena']['item_reward'] !== null && intval($this->data['arena']['item_reward']) !== 0) {
			foreach (explode(',', str_replace(' ', '', $this->data['arena']['item_reward'])) as $item) {
				$exp = explode(':', $item);
				if (isset($exp[0]) && isset($exp[0]) && isset($exp[0])) {
					list($id, $damage, $count) = $exp;
					if (Item::get($id, $damage, $count) instanceof Item) {
						$p->getInventory()->addItem(Item::get($id, $damage, $count));
					}
				}
			}
		}
		if (isset($this->data['arena']['money_reward'])) {
			if ($this->data['arena']['money_reward'] !== null && $this->plugin->economy !== null) {
				$money = $this->data['arena']['money_reward'];
				$ec = $this->plugin->economy;
				switch ($ec->getName()) {
					case "EconomyAPI":
						$ec->addMoney($p->getName(), $money);
						break;
					case "PocketMoney":
						$ec->setMoney($p->getName(), $ec->getMoney($p->getName()));
						break;
					case "MassiveEconomy":
						$ec->setMoney($p->getName(), $ec->getMoney($p->getName()));
						break;
					case "GoldStd":
						$ec->giveMoney($p, $money);
						break;
				}
				$p->sendMessage($this->plugin->getPrefix() . str_replace('%1', $money, $this->plugin->getMsg('get_money')));
			}
		}
	}

	public function unsetAllPlayers() {
		foreach ($this->players as $p) {
			$p->removeAllEffects();
			$this->loadInv($p);
			$p->setMaxHealth(20);
			$p->setMaxHealth($p->getMaxHealth());
			if ($p->getAttributeMap() != null) {//just to be really sure
				$p->setHealth(20);
				$p->setFood(20);
			}
			$sound = new EndermanTeleportSound(new Vector3());
			$sound->setComponents($p->x, $p->y, $p->z);
			$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
			$this->level->addSound($sound, [$p]);
			unset($this->players[strtolower($p->getName())]);
			$p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
		}
		foreach ($this->spec as $p) {
			$p->removeAllEffects();
			$this->loadInv($p);
			$p->setMaxHealth(20);
			$p->setMaxHealth($p->getMaxHealth());
			if ($p->getAttributeMap() != null) {//just to be really sure
				$p->setHealth(20);
				$p->setFood(20);
			}
			$sound = new EndermanTeleportSound(new Vector3());
			$sound->setComponents($p->x, $p->y, $p->z);
			$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
			$this->level->addSound($sound, [$p]);
			unset($this->spec[strtolower($p->getName())]);
			$p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
		}
		// Reset the arrays
		$this->claimedPedestals = [];
		$this->dead = [];
		$this->winners = [];
	}

	public function loadInv(Player $p) {
		if (!$p->isOnline()) {
			return;
		}
		$p->getInventory()->clearAll();
		foreach ($this->plugin->inv[strtolower($p->getName())] as $slot => $i) {
			list($id, $dmg, $count) = explode(":", $i);
			$item = Item::get($id, $dmg, $count);
			$p->getInventory()->setItem($slot, $item);
			unset($this->plugin->inv[strtolower($p->getName())]);
		}
	}

	public function getStatus(): string {
		if ($this->setup == true) {
			return "&6In setup";
		}
		if ($this->game === 0) {
			return "&fTap to join";
		}
		if ($this->game === 1) {
			return "&eRunning";
		}
		if (count($this->players) === $this->getMaxPlayers()) {
			return "&cFull";
		}
		return "&eUnknown";
	}

	public function getMaxPlayers() {
		return $this->data['arena']['max_players'];
	}

	public function getMinPlayers() {
		return $this->data['arena']['min_players'];
	}

	public function joinToArena(Player $p) {
		if ($p->hasPermission("sw.acces") || $p->isOp()) {
			if ($this->setup === true) {
				$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_in_setup'));
				return;
			}
			if (count($this->players) >= $this->getMaxPlayers()) { #|| !$p->hasPermission('sw.acces.full')) {
				$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('game_full'));
				return;
			}
			if ($this->game === 1) {
				$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ingame'));
				return;
			}
			if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
				$this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
			}
			if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
				$this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
			}
			$this->plugin->getServer()->getPluginManager()->callEvent($e = new PlayerJoinArenaEvent($this->plugin, $p, $this));
			if ($e->isCancelled()) {
				return;
			}
			$vars = ['%1'];
			$replace = [$p->getName()];
			$this->messageArenaPlayers(str_replace($vars, $replace, $this->plugin->getMsg('join_others')));
			$p->setGamemode(0);
			$this->saveInv($p);
			$this->players[strtolower($p->getName())] = $p;
			$sound = new EndermanTeleportSound(new Vector3());
			$sound->setComponents($p->x, $p->y, $p->z);
			$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
			$this->level->addSound($sound, [$p]);
			$spawn = $this->getNextPedestals($p);
			$p->teleport($spawn, 0, 0);
			$p->sendMessage(str_replace("%1", $p->getName(), $this->plugin->getPrefix() . $this->plugin->getMsg('join')));
			return;
		}
		$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('has_not_permission'));
	}

	public function saveInv(Player $p) {
		$items = [];

		foreach ($p->getInventory()->getContents() as $slot => $item) {
			$items[$slot] = implode(":", [$item->getId(), $item->getDamage(), $item->getCount()]);
		}
		$this->plugin->inv[strtolower($p->getName())] = $items;
		$p->getInventory()->clearAll();
	}

	public function getNextPedestals(Player $player): Position {
		$now = max($this->claimedPedestals);
		# Check if the value is 0
		if ($now === 0) {
			$this->claimedPedestals[$player->getName()] = $now;
			$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . ($now)];
			return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $this->level);
		}
		# Now the loop where to search if there is an empty space in cage
		for ($i = $now; $i !== 0; $i--) {
			if (array_search($i, $this->claimedPedestals)) {
				continue;
			}
			$this->claimedPedestals[$player->getName()] = $i;
			$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . ($i)];
			return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $this->level);
		}
		# Otherwise return the value + 1
		$now += 1;
		$this->claimedPedestals[$player->getName()] = $now;
		$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . ($now)];
		return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $this->level);
	}

	public function startGame() {
		$this->game = 1;
		$levelArena = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
		$sound = new AnvilUseSound(new Vector3());
		foreach ($this->players as $p) {
			if ($levelArena instanceof Level) {
				$this->refillChests();
			}
			$this->players[strtolower($p->getName())] = $p;
			if ($p instanceof Player) {
				$p->setMaxHealth($this->plugin->cfg->get("join_health"));
				$p->setMaxHealth($p->getMaxHealth());
				if ($p->getAttributeMap() != null) {//just to be really sure
					$p->setHealth($this->plugin->cfg->get("join_health"));
					$p->setFood(20);
				}
				$x = $p->getX();
				$y = $p->getY();
				$z = $p->getZ();
				$p->getLevel()->setBlock(new Vector3($x, $y - 1, $z), Block::get(0, 0));
				$sound->setComponents($p->x, $p->y, $p->z);
				$this->level->addSound($sound, [$p]);
			}
		}
		$this->messageArenaPlayers($this->plugin->getMsg('start_game'));
	}

	public function refillChests() {
		$contents = Utils::getChestContents();
		foreach ($this->level->getTiles() as $tile) {
			if ($tile instanceof Chest) {
				for ($i = 0; $i < $tile->getSize(); $i++) {
					$tile->getInventory()->setItem($i, Item::get(0));
				}
				if (empty($contents)) {
					$contents = Utils::getChestContents();
				}
				foreach (array_shift($contents) as $key => $val) {
					$tile->getInventory()->setItem($key, Item::get($val[0], 0, $val[1]));
				}
			}
		}
		unset($contents, $tile);
	}

	public function inArena(Entity $p): bool {
		if (!($p instanceof Player)) {
			return false;
		}
		$players = array_merge($this->players, $this->spec);
		return isset($players[strtolower($p->getName())]);
	}

	public function reloadArena() {
		if (strtolower($this->data['arena']['time'] !== "false")) {
			$this->level->setTime(str_replace(['day', 'night'], [6000, 18000], $this->data['arena']['time']));
			$this->level->stopTime();
		}
	}
}
