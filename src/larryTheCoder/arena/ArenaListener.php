<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
 *
 * Permission is hereby granted to any persons and/or organizations
 * using this software to copy, modify, merge, publish, and distribute it.
 * Said persons and/or organizations are not allowed to use the software or
 * any derivatives of the work for commercial use or any other means to generate
 * income, nor are they allowed to claim this software as their own.
 *
 * The persons and/or organizations are also disallowed from sub-licensing
 * and/or trademarking this software without explicit permission from larryTheCoder.
 *
 * Any persons and/or organizations using this software must disclose their
 * source code and have it publicly available, include this license,
 * provide sufficient credit to the original authors of the project (IE: larryTheCoder),
 * as well as provide a link to the original project.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
 * USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace larryTheCoder\arena;

use larryTheCoder\events\PlayerLoseArenaEvent;
use larryTheCoder\provider\SkyWarsDatabase;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\{event\server\DataPacketSendEvent, math\Vector3, Player, Server};
use pocketmine\event\block\{BlockBreakEvent, BlockPlaceEvent};
use pocketmine\event\entity\{EntityDamageByChildEntityEvent, EntityDamageByEntityEvent, EntityDamageEvent};
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerChatEvent,
	PlayerCommandPreprocessEvent,
	PlayerDeathEvent,
	PlayerDropItemEvent,
	PlayerInteractEvent,
	PlayerKickEvent,
	PlayerMoveEvent,
	PlayerQuitEvent,
	PlayerRespawnEvent};
use pocketmine\event\player\cheat\PlayerIllegalMoveEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\{Location, Position};
use pocketmine\network\mcpe\protocol\{BlockEventPacket,
	ClientboundMapItemDataPacket,
	LevelSoundEventPacket,
	MapInfoRequestPacket};
use pocketmine\utils\{Color, TextFormat};

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
	/** @var string[] */
	private $lastHit = [];
	/** @var int[] */
	private $cooldown = [];

	public function __construct(SkyWarsPE $plugin, Arena $arena){
		$this->plugin = $plugin;
		$this->arena = $arena;
	}

	/**
	 * @param PlayerMoveEvent $e
	 * @priority MONITOR
	 */
	public function onMove(PlayerMoveEvent $e){
		$p = $e->getPlayer();
		if($this->arena->inArena($p) && $this->arena->getMode() === Arena::ARENA_WAITING_PLAYERS && $p->isSurvival()){
			if(!isset($this->arena->spawnPedestals[$p->getName()])){
				return;
			}

			$loc = $this->arena->spawnPedestals[$p->getName()];

			if($e->getTo()->distance($loc) >= 1.25){
				$e->setTo(new Location($loc->getX(), $loc->getY(), $loc->getZ(), $p->yaw, $p->pitch, $p->getLevel()));
			}

			return;
		}
	}

	/**
	 * @param BlockPlaceEvent $e
	 * @priority MONITOR
	 */
	public function onPlaceEvent(BlockPlaceEvent $e){
		$p = $e->getPlayer();
		if($this->arena->inArena($p) && $p->isSurvival() && $this->arena->getMode() !== Arena::ARENA_RUNNING){
			$e->setCancelled(true);
		}
	}

	public function onPlayerCheat(PlayerIllegalMoveEvent $event){
		$event->setCancelled();
	}

	/**
	 * @param BlockBreakEvent $e
	 * @priority MONITOR
	 */
	public function onBreakEvent(BlockBreakEvent $e){
		$p = $e->getPlayer();
		if($this->arena->inArena($p) && $p->isSurvival() && $this->arena->getMode() !== Arena::ARENA_RUNNING){
			$e->setCancelled(true);
		}
	}

	/**
	 * This event priority been set to monitor
	 * to make sure it pass from the PureChat plugin
	 * priority.
	 *
	 * @param PlayerChatEvent $e
	 * @priority MONITOR
	 */
	public function onPlayerChat(PlayerChatEvent $e){
		$p = $e->getPlayer();
		if($this->arena->inArena($p) && Settings::$enableCListener === true){
			if($this->arena->getPlayerMode($p) === 0){
				$e->setRecipients($this->arena->players);
				$e->setFormat(str_replace(["&", "%1", "%2"], ["§", $p->getName(), $e->getMessage()], Settings::$chatFormatPlayer));
			}elseif($this->arena->getPlayerMode($p) === 1){
				$e->setRecipients(array_merge($this->arena->players, $this->arena->spec));
				$e->setFormat(str_replace(["&", "%1", "%2"], ["§", $p->getName(), $e->getMessage()], Settings::$chatFormatSpectator));
			}
			if(Settings::$chatSpy){
				$toAdminMessage = "§7[" . $p->getLevel()->getName() . "] §a" . $p->getName() . " §7> " . $e->getMessage();
				$this->plugin->getServer()->getLogger()->info($toAdminMessage);
				foreach(Server::getInstance()->getOnlinePlayers() as $player){
					if(($player->isOp() || $player->hasPermission("sw.chat.spy")) && !$this->arena->inArena($player)){
						$player->sendMessage($toAdminMessage);
					}
				}
			}

		}
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority LOWEST
	 */
	public function onPacketSent(DataPacketSendEvent $event){
		$pl = $event->getPlayer();
		$pk = $event->getPacket();
		if(!$this->arena->inArena($pl)){
			return;
		}
		switch(true):
			case ($pk instanceof BlockEventPacket):
				if($pk->eventType !== 1 && $pk->eventData === 0){
					break;
				}
				// This chest is being closed, prepare to cancel the packet.
				$pos = new Vector3($pk->x, $pk->y, $pk->z);

				$this->arena->chestId[] = $pos;
				$event->setCancelled();
				break;
		endswitch;
	}

	/**
	 * @param EntityDamageEvent $e
	 * @priority HIGHEST
	 */
	public function onHit(EntityDamageEvent $e){
		$now = time();
		$entity = $e->getEntity();

		$player = $entity instanceof Player ? $entity : null;
		# Maybe the player is attacking a chicken
		if($player === null){
			return;
		}
		# Player must be inside of arena otherwise its a fake
		if(!$this->arena->inArena($player)){
			return;
		}
		# Falling time isn't over yet
		if($this->arena->fallTime !== 0){
			$e->setCancelled(true);

			return;
		}
		# Arena not running yet cancel it
		if($this->arena->getMode() === Arena::ARENA_WAITING_PLAYERS){
			$e->setCancelled(true);

			return;
		}

		switch($e->getCause()){
			case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
				if($e instanceof EntityDamageByEntityEvent){
					$damage = $e->getDamager();
					if($damage instanceof Player){
						$this->lastHit[strtolower($player->getName())] = $damage->getName();
						$this->cooldown[strtolower($player->getName())] = $now + 30;
					}
				}else{
					if(isset($this->cooldown[strtolower($player->getName())])){
						if($this->cooldown[strtolower($player->getName())] - $now >= 0){
							break;
						}
						$this->lastHit[strtolower($player->getName())] = -1;// Member of illuminati?
						unset($this->cooldown[strtolower($player->getName())]);
						break;
					}
				}
				break;
			case EntityDamageEvent::CAUSE_PROJECTILE:
				if($e instanceof EntityDamageByChildEntityEvent){
					$damage = $e->getDamager();
					if($damage instanceof Player){
						$this->lastHit[strtolower($player->getName())] = $damage->getName();
						$this->cooldown[strtolower($player->getName())] = $now + 30;
						$volume = 0x10000000 * (min(30, 10) / 5); //No idea why such odd numbers, but this works...
						$damage->level->broadcastLevelSoundEvent($damage, LevelSoundEventPacket::SOUND_LEVELUP, 1, (int)$volume);
					}
				}else{
					$this->lastHit[strtolower($player->getName())] = $player->getName();
				}
				break;
			case EntityDamageEvent::CAUSE_MAGIC:
				if($e instanceof EntityDamageByEntityEvent || $e instanceof EntityDamageByChildEntityEvent){
					$damage = $e->getDamager();
					if($damage instanceof Player){
						$this->lastHit[strtolower($player->getName())] = $damage->getName();
						$this->cooldown[strtolower($player->getName())] = $now + 30;
					}
				}else{
					if(isset($this->cooldown[strtolower($player->getName())])){
						if($this->cooldown[strtolower($player->getName())] - $now >= 0){
							break;
						}
						$this->lastHit[strtolower($player->getName())] = $player->getNameTag();
						unset($this->cooldown[strtolower($player->getName())]);
						break;
					}
				}
				break;
			default:
				if(isset($this->cooldown[strtolower($player->getName())])){
					if($this->cooldown[strtolower($player->getName())] - $now >= 0){
						break;
					}
					$this->lastHit[strtolower($player->getName())] = $e->getCause();
					unset($this->cooldown[strtolower($player->getName())]);
					break;
				}
				break;
		}
	}

	/**
	 * @param PlayerDropItemEvent $e
	 * @priority HIGH
	 */
	public function onPlayerDropItem(PlayerDropItemEvent $e){
		$p = $e->getPlayer();

		if($this->arena->inArena($p) && $p->isSurvival() && $this->arena->getMode() !== Arena::ARENA_RUNNING){
			$e->setCancelled(true);
		}
	}

	/**
	 * @param PlayerDeathEvent $e
	 * @priority HIGH
	 */
	public function onPlayerDeath(PlayerDeathEvent $e){
		$p = $e->getPlayer();
		if($p instanceof Player && $this->arena->inArena($p)){
			if($this->arena->getPlayerMode($p) === 1){
				$e->setDeathMessage("");

				return;
			}
			if($this->arena->getPlayerMode($p) === 0){
				$e->setDeathMessage("");
				$e->setDrops([]);
				# Call the event
				$event = new PlayerLoseArenaEvent($this->plugin, $p, $this->arena);
				try{
					$event->call();
				}catch(\ReflectionException $e){
				}
				# Set the database data
				$this->setDeathData($p);

				$player = !isset($this->lastHit[strtolower($p->getName())]) ? $p->getName() : $this->lastHit[strtolower($p->getName())];
				if(!is_integer($player)){
					if(strtolower($player) === strtolower($p->getName())){
						$this->arena->messageArenaPlayers('death-message-suicide', false, ["{PLAYER}"], [$p->getName()]);
					}else{
						$this->arena->messageArenaPlayers('death-message', false, ["{PLAYER}", "{KILLER}"], [$p->getName(), $player]);
						$this->arena->kills[strtolower($player)]++;
					}
				}else{
					$msg = $this->getDeathMessageById($player);
					$this->arena->messageArenaPlayers($msg, false, ["{PLAYER}"], [$p->getName()]);
				}

				$this->arena->spec[strtolower($p->getName())] = $p;
				unset($this->arena->players[strtolower($p->getName())]);

				$this->arena->checkAlive();
				unset($this->lastHit[strtolower($p->getName())]);
			}

		}
	}

	private function setDeathData(Player $player){
		$pd = $this->plugin->getDatabase()->getPlayerData($player->getName());
		$pd->death++;
		$pd->lost++;
		$pd->kill += $this->arena->kills[strtolower($player->getName())];
		$pd->time += $this->arena->totalPlayed;
		$result = $this->plugin->getDatabase()->setPlayerData($player->getName(), $pd);
		if($result !== SkyWarsDatabase::DATA_EXECUTE_SUCCESS){
			Server::getInstance()->getLogger()->error($this->plugin->getPrefix() . "§4Unable to save " . $player->getName() . "'s data");
		}
	}

	public function getDeathMessageById(int $id): string{
		switch($id){
			case EntityDamageEvent::CAUSE_VOID:
				return "death-message-void";
			case EntityDamageEvent::CAUSE_SUICIDE:
				return "death-message-suicide";
			case EntityDamageEvent::CAUSE_SUFFOCATION:
				return "death-message-suffocated";
			case EntityDamageEvent::CAUSE_FIRE:
				return "death-message-burned";
			case EntityDamageEvent::CAUSE_CONTACT:
				return "death-message-catused";
			case EntityDamageEvent::CAUSE_FALL:
				return "death-message-fall";
			case EntityDamageEvent::CAUSE_LAVA:
				return "death-message-toasted";
			case EntityDamageEvent::CAUSE_DROWNING:
				return "death-message-drowned";
			case EntityDamageEvent::CAUSE_STARVATION:
				return "death-message-nature";
			case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
			case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION:
				return "death-message-explode";
			case EntityDamageEvent::CAUSE_CUSTOM:
				return "death-message-magic";
		}

		return "death-message-unknown";
	}

	/**
	 * @param PlayerRespawnEvent $e
	 * @priority MONITOR
	 */
	public function onRespawn(PlayerRespawnEvent $e){
		/** @var Player $d */
		$p = $e->getPlayer();
		if($this->arena->getPlayerMode($p) === 0){
			$p->setXpLevel(0);
			if($this->arena->data['arena']['spectator_mode'] === true){
				//$p->teleport(new Position($this->arena->data['arena']['spec_spawn_x'], $this->arena->data['arena']['spec_spawn_y'], $this->arena->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->arena->data['arena']['arena_world'])));
				$e->setRespawnPosition(new Position($this->arena->data['arena']['spec_spawn_x'], $this->arena->data['arena']['spec_spawn_y'], $this->arena->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->arena->data['arena']['arena_world'])));
				$p->setGamemode(3);
				$p->sendMessage($this->plugin->getMsg($p, 'player-spectate'));
				$this->arena->giveGameItems($p, true);

				foreach($this->arena->players as $p2){
					/** @var Player $p */
					if(($d = $this->arena->plugin->getServer()->getPlayer($p2)) instanceof Player){
						$d->hidePlayer($p);
					}
				}

				return;
			}else{
				unset($this->arena->players[strtolower($p->getName())]);
				$e->setRespawnPosition($this->plugin->getServer()->getLevelByName("world")->getSpawnLocation());

				return;
			}
		}
		if($this->arena->getPlayerMode($p) === 1){
			$p->setXpLevel(0);
			$e->setRespawnPosition($this->plugin->getServer()->getLevelByName("world")->getSpawnLocation());

			return;
		}
	}

	/**
	 * @param PlayerInteractEvent $e
	 * @priority NORMAL
	 */
	public function onBlockTouch(PlayerInteractEvent $e){
		$p = $e->getPlayer();
		$b = $e->getBlock();
		# Player is interacting with game signs
		if($b->x == $this->arena->data["signs"]["join_sign_x"] && $b->y === $this->arena->data["signs"]["join_sign_y"] && $b->z == $this->arena->data["signs"]["join_sign_z"] && $b->level === $this->plugin->getServer()->getLevelByName($this->arena->data["signs"]["join_sign_world"])){
			if($this->arena->getPlayerMode($p) === 0 || $this->arena->getPlayerMode($p) === 1){
				return;
			}
			$this->arena->joinToArena($p);
		}

		# Check if player in the arena, then it must be an item interaction
		if($this->arena->inArena($p) && Settings::$enableSpecialItem && isset(Settings::$items[TextFormat::clean($e->getItem()->getCustomName())])){
			Server::getInstance()->dispatchCommand($p, $e->getItem()->getNamedTag()->getString("command"));
			$e->setCancelled();
		}
	}

	public function playerQuitEvent(PlayerQuitEvent $event){
		if($this->arena->inArena($event->getPlayer())){
			$this->arena->leaveArena($event->getPlayer(), true);
			$this->arena->checkAlive();
		}
	}

	public function playerKickedEvent(PlayerKickEvent $event){
		if($this->arena->inArena($event->getPlayer())){
			$this->arena->leaveArena($event->getPlayer(), true);
			$this->arena->checkAlive();
		}
	}

	public function onDataPacket(DataPacketReceiveEvent $event){
		$packet = $event->getPacket();
		$p = $event->getPlayer();
		if($packet instanceof MapInfoRequestPacket){
			if($packet->mapId === 18293883){
				$folder = SkyWarsPE::getInstance()->getDataFolder() . "image/";
				$colors = [];
				$imaged = @imagecreatefrompng($folder . "map.png");
				if(!$imaged){
					Utils::sendDebug("Error: Cannot load map");

					return;
				}
				$anchor = 128;
				$altars = 128;
				$imaged = imagescale($imaged, $anchor, $altars, IMG_NEAREST_NEIGHBOUR);
				imagepng($imaged, $folder . "map.png");
				for($y = 0; $y < $altars; ++$y){
					for($x = 0; $x < $anchor; ++$x){
						$rgb = imagecolorat($imaged, $x, $y);
						$color = imagecolorsforindex($imaged, $rgb);
						$r = $color["red"];
						$g = $color["green"];
						$b = $color["blue"];
						$colors[$y][$x] = new Color($r, $g, $b, 0xff);
					}
				}

				$pk = new ClientboundMapItemDataPacket();
				$pk->mapId = 18293883;
				$pk->type = ClientboundMapItemDataPacket::BITFLAG_TEXTURE_UPDATE;
				$pk->height = 128;
				$pk->width = 128;
				$pk->scale = 1;
				$pk->colors = $colors;
				$p->dataPacket($pk);
			}
		}
	}

	public function onCommand(PlayerCommandPreprocessEvent $ev){
		$cmd = strtolower($ev->getMessage());
		$p = $ev->getPlayer();
		if($cmd{0} == '/'){
			$cmd = explode(' ', $cmd)[0];
			// In arena, no permission, is alive, arena started === cannot use command.
			$val = $this->arena->inArena($p)
				&& !$p->hasPermission("sw.admin.bypass")
				&& $this->arena->getPlayerMode($p) === 0
				&& !$this->arena->inAcceptedMode();
			if($val){
				if(!in_array($cmd, Settings::$acceptedCommand) && $cmd !== "sw"){
					$ev->getPlayer()->sendMessage($this->plugin->getMsg($p, "banned-command"));
					$ev->setCancelled(true);
				}
			}
		}
		unset($cmd);
	}

}
