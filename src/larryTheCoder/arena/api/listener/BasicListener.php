<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2019 larryTheCoder and contributors
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

namespace larryTheCoder\arena\api\listener;

use larryTheCoder\arena\api\DefaultGameAPI;
use larryTheCoder\arena\Arena;
use larryTheCoder\arena\State;
use larryTheCoder\provider\SkyWarsDatabase;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\Server;

/**
 * A very basic listener for the arena.
 * This concept is basically to handle player as-it-should be handled
 * by the arena itself.
 *
 * @package larryTheCoder\arena\api\listener
 */
class BasicListener implements Listener {

	/** @var DefaultGameAPI */
	private $gameAPI;
	/**@var Arena */
	private $arena;
	/** @var string[] */
	private $lastHit = [];
	/** @var int[] */
	private $cooldown = [];

	public function __construct(DefaultGameAPI $api){
		$this->gameAPI = $api;
		$this->arena = $api->arena;
	}

	/**
	 * Handles the player movement. During STATE_WAITING or STATE_SLOPE_WAITING,
	 * player wont be able to get out from the cage area, but were able to move within
	 * the cage.
	 *
	 * @param PlayerMoveEvent $e
	 * @priority MONITOR
	 */
	public function onMove(PlayerMoveEvent $e){
		$p = $e->getPlayer();
		if($this->arena->isInArena($p) && $this->arena->getStatus() <= State::STATE_SLOPE_WAITING && $p->isSurvival()){
			if(!isset($this->arena->usedPedestals[$p->getName()])){
				return;
			}

			/** @var Vector3 $loc */
			$loc = $this->arena->usedPedestals[$p->getName()][0];

			if(($loc->getY() - $e->getTo()->getY()) >= 1.55){
				$e->setTo(new Location($loc->getX(), $loc->getY(), $loc->getZ(), $p->yaw, $p->pitch, $p->getLevel()));
			}

			return;
		}
	}

	/**
	 * Handles player block placement in the arena. If the arena is not in STATE_ARENA_RUNNING,
	 * player wont be able to place blocks within the arena.
	 *
	 * @param BlockPlaceEvent $e
	 * @priority MONITOR
	 */
	public function onPlaceEvent(BlockPlaceEvent $e){
		$p = $e->getPlayer();
		if($this->arena->isInArena($p) && $p->isSurvival() && $this->arena->getStatus() !== State::STATE_ARENA_RUNNING){
			$e->setCancelled(true);
		}
	}

	/**
	 * Handles player block placement in the arena. If the arena is not in STATE_ARENA_RUNNING,
	 * player wont be able to place blocks within the arena.
	 *
	 * @param BlockBreakEvent $e
	 * @priority MONITOR
	 */
	public function onBreakEvent(BlockBreakEvent $e){
		$p = $e->getPlayer();
		if($this->arena->isInArena($p) && $p->isSurvival() && $this->arena->getStatus() !== State::STATE_ARENA_RUNNING){
			$e->setCancelled(true);
		}
	}

	/**
	 * Handles player damages towards another players. This event is to log player's damages towards
	 * another player entity. This is required to check who actually killed this player.
	 *
	 * @param EntityDamageEvent $e
	 * @priority HIGHEST
	 */
	public function onHit(EntityDamageEvent $e){
		$now = time();
		$entity = $e->getEntity();

		$player = $entity instanceof Player ? $entity : null;
		# Maybe this player is attacking a chicken
		if($player === null){
			return;
		}
		# Player must be inside of arena otherwise its a fake
		if(!$this->arena->isInArena($player)){
			return;
		}
		# Falling time isn't over yet
		if($this->gameAPI->fallTime !== 0){
			$e->setCancelled(true);

			return;
		}
		# Arena not running yet cancel it
		if($this->arena->getStatus() != State::STATE_ARENA_RUNNING){
			$e->setCancelled(true);

			return;
		}

		// TODO: Assists killing.
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
	 * Handles player deaths. During this event, a piece of data that contains the last
	 * time player gets damaged from {@see BasicListener::onHit()} is analyzed and the message
	 * will get broadcasted to all of the players in the arena.
	 *
	 * @param PlayerDeathEvent $e
	 * @priority HIGH
	 */
	public function onPlayerDeath(PlayerDeathEvent $e){
		$p = $e->getPlayer();
		if($p instanceof Player && $this->arena->isInArena($p)){
			$e->setDeathMessage("");

			if($this->arena->getPlayerState($p) === State::PLAYER_ALIVE){
				$e->setDrops([]);
				# Set the database data
				$this->setDeathData($p);

				$player = !isset($this->lastHit[strtolower($p->getName())]) ? $p->getName() : $this->lastHit[strtolower($p->getName())];
				if(!is_integer($player)){
					if(strtolower($player) === strtolower($p->getName())){
						$this->arena->messageArenaPlayers('death-message-suicide', false, ["{PLAYER}"], [$p->getName()]);
					}else{
						$this->arena->messageArenaPlayers('death-message', false, ["{PLAYER}", "{KILLER}"], [$p->getName(), $player]);
						$this->gameAPI->kills[strtolower($player)]++;
					}
				}else{
					$msg = Utils::getDeathMessageById($player);
					$this->arena->messageArenaPlayers($msg, false, ["{PLAYER}"], [$p->getName()]);
				}
				unset($this->lastHit[strtolower($p->getName())]);

				$this->arena->knockedOut($p);
			}
		}
	}

	private function setDeathData(Player $player){
		$pd = $this->gameAPI->plugin->getDatabase()->getPlayerData($player->getName());
		$pd->death++;
		$pd->lost++;
		$pd->kill += $this->gameAPI->kills[strtolower($player->getName())];
		$pd->time += (microtime(true) - $this->arena->startedTime);

		$result = $this->gameAPI->plugin->getDatabase()->setPlayerData($player->getName(), $pd);
		if($result !== SkyWarsDatabase::DATA_EXECUTE_SUCCESS){
			Utils::send("§cUnable to save " . $player->getName() . "'s data");
		}
	}

	/**
	 * Handles player's respawn after player's death. During this event, they will check
	 * if the player is in spectator mode, as shown in {@see Arena::knockedOut()}, otherwise
	 * we set the player respawn point to the lobby.
	 *
	 * @param PlayerRespawnEvent $e
	 * @priority MONITOR
	 */
	public function onRespawn(PlayerRespawnEvent $e){
		/** @var Player $d */
		$p = $e->getPlayer();
		# Player must be inside of arena otherwise its a fake
		if(!$this->arena->isInArena($p)){
			return;
		}

		if($this->arena->getPlayerState($p) === State::PLAYER_SPECTATE){
			$p->setXpLevel(0);
			if($this->arena->enableSpectator){
				$e->setRespawnPosition(Position::fromObject($this->arena->arenaSpecPos, $this->arena->getLevel()));
				$p->setGamemode(Player::SPECTATOR);
				$p->sendMessage($this->gameAPI->plugin->getMsg($p, 'player-spectate'));
				$this->gameAPI->giveGameItems($p, true);

				foreach($this->arena->getPlayers() as $p2){
					/** @var Player $p */
					if(($d = Server::getInstance()->getPlayer($p2)) instanceof Player){
						$d->hidePlayer($p);
					}
				}
			}else{
				$e->setRespawnPosition($this->gameAPI->plugin->getDatabase()->getLobby());
			}
		}
	}

	/**
	 * Handles player interaction with the arena signs.
	 *
	 * @param PlayerInteractEvent $e
	 * @priority NORMAL
	 */
	public function onBlockTouch(PlayerInteractEvent $e){
		Utils::loadFirst($this->arena->joinSignWorld, true);

		$p = $e->getPlayer();
		$b = $e->getBlock();

		# Player is interacting with game signs
		if($b->equals(Position::fromObject($this->arena->joinSignVec, Server::getInstance()->getLevelByName($this->arena->joinSignWorld)))){
			$this->arena->joinToArena($p);
		}
	}

	public function playerQuitEvent(PlayerQuitEvent $event){
		if($this->arena->isInArena($event->getPlayer())){
			$this->arena->leaveArena($event->getPlayer(), true);
			$this->arena->checkAlive();
		}
	}

	public function playerKickedEvent(PlayerKickEvent $event){
		if($this->arena->isInArena($event->getPlayer())){
			$this->arena->leaveArena($event->getPlayer(), true);
			$this->arena->checkAlive();
		}
	}

	/**
	 * Handles player commands thru a severe permissions checks.
	 * This prevents the player from using a command that is forbidden to this game.
	 *
	 * @param PlayerCommandPreprocessEvent $ev
	 */
	public function onCommand(PlayerCommandPreprocessEvent $ev){
		$cmd = strtolower($ev->getMessage());
		$p = $ev->getPlayer();
		if($cmd{0} == '/'){
			$cmd = explode(' ', $cmd)[0];
			// In arena, no permission, is alive, arena started === cannot use command.
			$val = $this->arena->isInArena($p)
				&& !$p->hasPermission("sw.admin.bypass")
				&& $this->arena->getPlayerState($p) === State::PLAYER_ALIVE
				&& $this->arena->getStatus() === State::STATE_ARENA_RUNNING;
			if($val){
				if(!in_array($cmd, Settings::$acceptedCommand) && $cmd !== "sw"){
					$ev->getPlayer()->sendMessage($this->gameAPI->plugin->getMsg($p, "banned-command"));
					$ev->setCancelled(true);
				}
			}
		}

		unset($cmd);
	}


}