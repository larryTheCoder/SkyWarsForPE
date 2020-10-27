<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
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

declare(strict_types = 1);

namespace larryTheCoder\arena\api\listener;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaListener;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\ArenaImpl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;

/**
 * A very basic listener for the arena.
 * This concept is basically to handle player as-it-should be handled
 * by the arena itself.
 *
 * @package larryTheCoder\arenaRewrite\api\listener
 */
class BasicListener implements Listener {

	/**@var Arena */
	private $arena;

	/** @var string[]|int[] */
	private $lastHit = [];
	/** @var int[] */
	private $cooldown = [];
	/** @var ArenaListener */
	private $listener;

	public function __construct(Arena $arena){
		$this->arena = $arena;
		$this->listener = $arena->getEventListener();
	}

	/**
	 * Handles the player movement. During STATE_WAITING or STATE_SLOPE_WAITING, player wont
	 * be able to get out from the cage area, but were able to move within the cage.
	 *
	 * @param PlayerMoveEvent $e
	 * @priority HIGHEST
	 */
	public function onMove(PlayerMoveEvent $e): void{
		$p = $e->getPlayer();

		$pm = $this->arena->getPlayerManager();
		if(!$pm->isInArena($p)) return;
		if($this->arena->getStatus() <= ArenaState::STATE_STARTING && $p->isSurvival()){
			$cage = $this->arena->getCageManager()->getCage($p);
			if($cage === null){
				goto handleMovement;
			}

			$newVec = $p->floor();
			if($newVec->distance($cage) >= 3){
				$p->teleport($cage->add(new Vector3(0.5, 0, 0.5)));
				$e->setCancelled();
			}

			return;
		}

		handleMovement:
		$this->listener->onPlayerMove($e);
	}

	/**
	 * Handles player block placement in the arena. If the arena is not in STATE_ARENA_RUNNING,
	 * player wont be able to place blocks within the arena.
	 *
	 * @param BlockPlaceEvent $e
	 * @priority HIGHEST
	 */
	public function onPlaceEvent(BlockPlaceEvent $e): void{
		$p = $e->getPlayer();

		$pm = $this->arena->getPlayerManager();
		if(!$pm->isInArena($p)) return;
		if($p->isSurvival() && $this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
			$e->setCancelled(true);

			return;
		}

		$this->listener->onBlockPlaceEvent($e);
	}

	/**
	 * Handles player block breaks in the arena. If the arena is not in STATE_ARENA_RUNNING,
	 * player wont be able to break blocks within the arena.
	 *
	 * @param BlockBreakEvent $e
	 * @priority HIGHEST
	 */
	public function onBreakEvent(BlockBreakEvent $e): void{
		$p = $e->getPlayer();

		$pm = $this->arena->getPlayerManager();
		if(!$pm->isInArena($p)) return;
		if($p->isSurvival() && $this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
			$e->setCancelled(true);
		}

		$this->listener->onBlockBreakEvent($e);
	}

	public function onPlayerQuitEvent(PlayerQuitEvent $e): void{
		$p = $e->getPlayer();

		$pm = $this->arena->getPlayerManager();
		if($pm->isInArena($p)){
			$this->listener->onPlayerQuitEvent($e);
		}
	}

	/**
	 * Handles player damages towards another players. This event is to log player's damages towards
	 * another player entity. This is required to check who actually killed this player.
	 *
	 * @param EntityDamageEvent $e
	 * @priority HIGHEST
	 */
	public function onHit(EntityDamageEvent $e): void{
		$now = time();
		$entity = $e->getEntity();

		$player = $entity instanceof Player ? $entity : null;
		# Maybe this player is attacking a chicken
		if($player === null){
			return;
		}
		# Player must be inside of arena otherwise its a fake
		$pm = $this->arena->getPlayerManager();
		if(!$pm->isInArena($player)) return;

		# Arena not running yet cancel it
		if($this->arena->getStatus() != ArenaState::STATE_ARENA_RUNNING){
			$e->setCancelled(true);

			return;
		}

		$this->listener->onPlayerHitEvent($e);
		if($e->isCancelled()) return;

		// In order to remove "death" loading screen. Immediate respawn.
		$health = $player->getHealth() - $e->getFinalDamage();
		if($health <= 0){
			$e->setCancelled();

			$target = -1;
			$playerName = !isset($this->lastHit[$player->getName()]) ? $player->getName() : $this->lastHit[$player->getName()];
			if(!is_integer($playerName)){
				if($playerName === $player->getName()){
					$pm->broadcastToPlayers('death-message-suicide', false, ["{PLAYER}"], [$player->getName()]);
				}else{
					$pm->broadcastToPlayers('death-message', false, ["{PLAYER}", "{KILLER}"], [$player->getName(), $target = $playerName]);

					$pm->addKills($target);
				}
			}else{
				$target = self::getDeathMessageById($playerName);
				$pm->broadcastToPlayers($target, false, ["{PLAYER}"], [$player->getName()]);
			}
			unset($this->lastHit[$player->getName()]);

			$this->listener->onPlayerDeath($player, $target);
		}else{
			switch($e->getCause()){
				case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
					if($e instanceof EntityDamageByEntityEvent){
						$damage = $e->getDamager();
						if($damage instanceof Player){
							$this->lastHit[$player->getName()] = $damage->getName();
							$this->cooldown[$player->getName()] = $now + 30;
						}
					}else{
						if(isset($this->cooldown[$player->getName()])){
							if($this->cooldown[$player->getName()] - $now >= 0){
								break;
							}
							$this->lastHit[$player->getName()] = -1;// Member of illuminati?
							unset($this->cooldown[$player->getName()]);
							break;
						}
					}
					break;
				case EntityDamageEvent::CAUSE_PROJECTILE:
					if($e instanceof EntityDamageByChildEntityEvent){
						$damage = $e->getDamager();
						if($damage instanceof Player){
							$this->lastHit[$player->getName()] = $damage->getName();
							$this->cooldown[$player->getName()] = $now + 30;
							$volume = 0x10000000 * (min(30, 10) / 5); //No idea why such odd numbers, but this works...
							$damage->level->broadcastLevelSoundEvent($damage, LevelSoundEventPacket::SOUND_LEVELUP, 1, (int)$volume);
						}
					}else{
						$this->lastHit[$player->getName()] = $player->getName();
					}
					break;
				case EntityDamageEvent::CAUSE_MAGIC:
					if($e instanceof EntityDamageByChildEntityEvent){
						$damage = $e->getChild();
					}elseif($e instanceof EntityDamageByEntityEvent){
						$damage = $e->getDamager();
					}else{
						if(isset($this->cooldown[$player->getName()])){
							if($this->cooldown[$player->getName()] - $now >= 0){
								break;
							}

							$this->lastHit[$player->getName()] = $player->getNameTag();
							unset($this->cooldown[$player->getName()]);
							break;
						}

						return;
					}

					if($damage instanceof Player){
						$this->lastHit[$player->getName()] = $damage->getName();
						$this->cooldown[$player->getName()] = $now + 30;
					}
					break;
				default:
					if(isset($this->cooldown[$player->getName()])){
						if($this->cooldown[$player->getName()] - $now >= 0){
							break;
						}
						$this->lastHit[$player->getName()] = $e->getCause();
						unset($this->cooldown[$player->getName()]);
						break;
					}
					break;
			}
		}
	}

	public static function getDeathMessageById(int $id){
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
	 * @param PlayerKickEvent $event
	 * @priority NORMAL
	 */
	public function playerKickEvent(PlayerKickEvent $event): void{
		if($this->arena->getPlayerManager()->isInArena($event->getPlayer())){
			$this->arena->leaveArena($event->getPlayer(), true);
		}
	}

	/**
	 * Handles player commands thru a severe permissions checks.
	 * This prevents the player from using a command that is forbidden to this game.
	 *
	 * @param PlayerCommandPreprocessEvent $ev
	 * @priority NORMAL
	 */
	public function onCommand(PlayerCommandPreprocessEvent $ev): void{
		$cmd = strtolower($ev->getMessage());
		$p = $ev->getPlayer();
		if($cmd[0] === '/' && $this->arena->getPlayerManager()->isInArena($p)){
			$this->listener->onPlayerExecuteCommand($ev);
		}
	}

	/**
	 * Handles player interaction with the arena signs.
	 *
	 * @param PlayerInteractEvent $e
	 * @priority NORMAL
	 */
	public function onPlayerInteract(PlayerInteractEvent $e){
		$p = $e->getPlayer();

		if($this->arena->getPlayerManager()->isInArena($p)){
			$this->listener->onPlayerInteractEvent($e);

			if($e->getItem() === ArenaImpl::getLeaveItem()){
				$this->arena->leaveArena($p);
			}

			return;
		}

		$signManager = $this->arena->getSignManager();
		if($signManager === null) return;

		$signManager->onInteract($e);
	}
}