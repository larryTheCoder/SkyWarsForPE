<?php

namespace larryTheCoder\arena;

use larryTheCoder\events\PlayerJoinArenaEvent;
use larryTheCoder\events\PlayerLoseArenaEvent;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\Player;
use pocketmine\utils\Config;

/**
 * A Listener that will be listen to any Events that will be called
 *
 * @package larryTheCoder\arena
 */
class ArenaListener implements Listener {

	/** @var SkyWarsPE */
	private $plugin;

	/** @var Arena */
	private $arena;
	private $tap;

	public function __construct(SkyWarsPE $plugin, Arena $arena) {
		$this->plugin = $plugin;
		$this->arena = $arena;
	}

	/**
	 * @param PlayerMoveEvent $e
	 * @priority MONITOR
	 */
	public function onMove(PlayerMoveEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->arena->game !== 1 && $p->getGamemode() == 0) {
			$to = clone $e->getFrom();
			$to->yaw = $e->getTo()->yaw;
			$to->pitch = $e->getTo()->pitch;
			$e->setTo($to);
			return;
		}
		if ($this->arena->game !== 0) {
			$e->getHandlers()->unregister($this);
		}
	}

	/**
	 * @param BlockPlaceEvent $e
	 * @priority MONITOR
	 */
	public function onPlaceEvent(BlockPlaceEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->arena->game === 0) {
			$e->setCancelled(true);
		}
	}

	/**
	 * @param BlockBreakEvent $e
	 * @priority MONITOR
	 */
	public function onBreakEvent(BlockBreakEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->arena->game === 0) {
			$e->setCancelled(true);
		}
	}

	/**
	 * Priority is the MONITOR so it can pass PureChat plugin priority.
	 *
	 * @param PlayerChatEvent $e
	 * @priority MONITOR
	 */
	public function onPlayerChat(PlayerChatEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->plugin->cfg->getNested("chat.enable_chat") === true) {
			if ($this->arena->getPlayerMode($p) === 0) {
				$e->setRecipients($this->arena->players);
				$e->setFormat(str_replace(["&", "%1", "%2"], ["§", $p->getName(), $e->getMessage()], "&b[Player] &a%1 > &f%2"));
			} else if ($this->arena->getPlayerMode($p) === 1) {
				$e->setRecipients(array_merge($this->arena->players, $this->arena->spec));
				$e->setFormat(str_replace(["&", "%1", "%2"], ["§", $p->getName(), $e->getMessage()], "&7[Spectator] &8%1 > &f%2"));
			}
		}
	}

	/**
	 * @param PlayerItemHeldEvent $e
	 *
	 * @priority HIGH
	 */
	public function onItemHeld(PlayerItemHeldEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->plugin->cfg->getNested("item.enable_leave_item") === true) {
			if ($this->arena->getPlayerMode($p) === 1) {
				if ($this->arena->plugin->cfg->get("enable-double-tap")) {
					$now = microtime(true);
					# its took 5 seconds to remove player leave queue
					if (!isset($this->tap[$p->getName()]) or $now - $this->tap[$p->getName()][1] >= 5) {
						$this->tap[$p->getName()] = $now;
						$p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg("tap-again"));
						return;
					} else {
						unset($this->tap[$p->getName()]);
					}
				}
				if (($e->getItem()->getId() . ':' . $e->getItem()->getDamage()) === $this->plugin->cfg->getNested("item.leave_item")) {
					$this->arena->leaveArena($p);
				}
			}
		}
	}

	/**
	 * @param EntityDamageEvent $e
	 * @priority NORMAL
	 */
	public function onHit(EntityDamageEvent $e) {
		$entity = $e->getEntity();

		$player = $entity instanceof Player;

		if ($this->arena->fallTime !== 0) {
			$e->setCancelled(true);
			return;
		}
		if ($e instanceof EntityDamageByEntityEvent || $e instanceof EntityDamageByChildEntityEvent) {
			$damage = $e->getDamager();
			if (!($damage instanceof Player && $this->arena->inArena($damage) && $this->arena->game <= 1 || $player && $this->arena->inArena($entity) && $this->arena->game <= 1)) {
				$e->setCancelled(true);
				return;
			}
		} else if (!$player) {
			return;
		} else if ($entity instanceof Player && $this->arena->inArena($entity) && $this->arena->game <= 1) {
			$e->setCancelled(false);
			return;
		}
	}

	/**
	 * @param PlayerDropItemEvent $e
	 * @priority HIGH
	 */
	public function onPlayerPickup(PlayerDropItemEvent $e) {
		$p = $e->getPlayer();
		if ($this->arena->inArena($p) && $this->arena->game === 0 || $this->plugin->cfg->get("allow_drop_item") === true) {
			$e->setCancelled(true);
		}
	}

	/**
	 * @param PlayerDeathEvent $e
	 * @priority NORMAL
	 */
	public function onPlayerDeath(PlayerDeathEvent $e) {
		$p = $e->getPlayer();
		$n = $e->getPlayer()->getName();
		if ($p instanceof Player) {
			if (!$this->arena->inArena($p)) {
				return;
			}
			if ($this->arena->getPlayerMode($p) === 1) {
				$e->setDeathMessage("");
				return;
			}
			if ($this->arena->getPlayerMode($p) === 0) {
				$this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerLoseArenaEvent($this->plugin, $p, $this->arena));
				$e->setDeathMessage("");
				$e->setDrops([]);
				$this->arena->dead[strtolower($n)] = true;
				unset($this->arena->players[strtolower($n)]);
				Utils::strikeLightning($p);
				$this->arena->messageArenaPlayers(str_replace(['%2', '%1'], [count($this->arena->players), $n], $this->plugin->getMsg('death')));
				$this->arena->checkAlive();
			}
		}
	}

	/**
	 * @param PlayerRespawnEvent $e
	 * @priority MONITOR
	 */
	public function onRespawn(PlayerRespawnEvent $e) {
		/** @var Player $p */
		/** @var Player $d */
		$p = $e->getPlayer();
		if ($this->arena->getPlayerMode($p) === 0) {
			if ($this->arena->data['arena']['spectator_mode'] == 'true') {
				$e->setRespawnPosition(new Position($this->arena->data['arena']['spec_spawn_x'], $this->arena->data['arena']['spec_spawn_y'], $this->arena->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->arena->data['arena']['arena_world'])));
				unset($this->arena->players[strtolower($p->getName())]);
				$this->arena->spec[strtolower($p->getName())] = $p;
				$p->setGamemode(Player::CREATIVE);
				foreach ($this->arena->players as $p2) {
					if (($d = $this->arena->plugin->getServer()->getPlayer($p2)) instanceof Player) {
						$d->hidePlayer($p);
					}
				}
				$pk = new ContainerSetContentPacket();
				$pk->windowid = ContainerIds::INVENTORY;
				$p->dataPacket($pk);
				$idMeta = explode(':', $this->plugin->cfg->getNested("item.leave_item"));
				$p->getInventory()->setHeldItemIndex(1);
				$p->getInventory()->setItem(0, Item::get((int)$idMeta[0], (int)$idMeta[1], 1));
				$p->getInventory()->setHotbarSlotIndex(0, 0);
				$p->getInventory()->sendContents($p);
				$p->getInventory()->sendContents($p->getViewers());
				$p->sendMessage($this->plugin->getMsg("death_spectate"));
				return;
			}
			unset($this->arena->players[strtolower($p->getName())]);
			$e->setRespawnPosition(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
			return;
		}
		if ($this->arena->getPlayerMode($p) === 1) {
			$e->setRespawnPosition(new Position($this->arena->data['arena']['spec_spawn_x'], $this->arena->data['arena']['spec_spawn_y'], $this->arena->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->arena->data['arena']['arena_world'])));
			return;
		}
	}

	/**
	 * @param PlayerInteractEvent $e
	 * @priority NORMAL
	 */
	public function onBlockTouch(PlayerInteractEvent $e) {
		$b = $e->getBlock();
		$p = $e->getPlayer();
		if ($p->hasPermission("sw.sign") || $p->isOp()) {
			if ($b->x == $this->arena->data["signs"]["join_sign_x"] && $b->y == $this->arena->data["signs"]["join_sign_y"] && $b->z == $this->arena->data["signs"]["join_sign_z"] && $b->level === $this->plugin->getServer()->getLevelByName($this->arena->data["signs"]["join_sign_world"])) {
				if ($this->arena->getPlayerMode($p) === 0 || $this->arena->getPlayerMode($p) === 1) {
					return;
				}
				$this->arena->joinToArena($p);
			}
			if ($b->x == $this->arena->data["signs"]["return_sign_x"] && $b->y == $this->arena->data["signs"]["return_sign_y"] && $b->z == $this->arena->data["signs"]["return_sign_z"] && $b->level === $this->plugin->getServer()->getLevelByName($this->arena->data["arena"]["arena_world"])) {
				if ($this->arena->getPlayerMode($p) === 1) {
					$this->arena->leaveArena($p);
				}
			}
			return;
		}
		$p->sendMessage($this->plugin->getMsg('has_not_permission'));
	}

	/**
	 * @param PlayerJoinArenaEvent $e
	 *
	 * @priority HIGH
	 */
	public function PlayerJoinArenaEvent(PlayerJoinArenaEvent $e) {
		$p = $e->getPlayer();
		$ban = new Config($this->plugin->getDataFolder() . "players/{$p->getName()}.yml", Config::YAML);
		# check if the player is banned
		if ($ban->get("ban") === true) {
			# Player is banned
			$e->setCancelled(true);
			$p->sendMessage($this->plugin->getMsg("banned"));
			return;
		}
	}

	public function onCommand(PlayerCommandPreprocessEvent $ev) {
		$cmd = strtolower($ev->getMessage());
		$p = $ev->getPlayer();
		if ($cmd{0} == '/') {
			$cmd = explode(' ', $cmd)[0];
			if ($this->arena->inArena($p)) {
				if (in_array($cmd, $this->plugin->cfg->get("banned_command_in_game"))) {
					$ev->getPlayer()->sendMessage($this->plugin->getMsg("banned_command"));
					$ev->setCancelled(true);
				}
			}
		}
		unset($cmd);
	}

}
