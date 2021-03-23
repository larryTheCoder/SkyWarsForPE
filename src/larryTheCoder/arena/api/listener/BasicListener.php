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
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\ArenaImpl;
use pocketmine\entity\Human;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\math\Vector3;
use pocketmine\Player;

/**
 * A singleton basic listener used to listen for appropriate events to all arenas
 * enabled, providing better insight for debugging and timings checks.
 */
abstract class BasicListener implements Listener {

	/**
	 * Returns the arena that this player is in.
	 *
	 * @param Human $player
	 * @return ArenaImpl|null
	 */
	public abstract function getArena(Human $player): ?Arena;

	/**
	 * Handles the player movement. During STATE_WAITING or STATE_SLOPE_WAITING, player wont
	 * be able to get out from the cage area, but were able to move within the cage.
	 *
	 * @param PlayerMoveEvent $e
	 * @priority HIGHEST
	 */
	public function onMove(PlayerMoveEvent $e): void{
		$p = $e->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		if($arena->getStatus() <= ArenaState::STATE_STARTING && $p->isSurvival()){
			$cage = $arena->getCageManager()->getCage($p);
			if($cage === null){
				goto handleMovement;
			}

			$newVec = $p->floor();
			if($newVec->distance($cage) >= 3){
				$p->teleport($cage->add(new Vector3(0.5, 0, 0.5)));
			}

			return;
		}

		handleMovement:
		$arena->getEventListener()->onPlayerMove($e);
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

		$arena = $this->getArena($p);
		if($arena === null) return;

		if($p->isSurvival() && $arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
			$e->setCancelled(true);

			return;
		}

		$arena->getEventListener()->onBlockPlaceEvent($e);
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

		$arena = $this->getArena($p);
		if($arena === null) return;

		if($p->isSurvival() && $arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
			$e->setCancelled(true);
		}

		$arena->getEventListener()->onBlockBreakEvent($e);
	}

	public function onPlayerQuitEvent(PlayerQuitEvent $e): void{
		$p = $e->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		$arena->getEventListener()->onPlayerQuitEvent($e);
	}

	public function onInventoryOpenEvent(InventoryOpenEvent $event): void{
		$p = $event->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		$arena->getEventListener()->onInventoryOpenEvent($event);
	}

	public function onPlayerExhaustEvent(PlayerExhaustEvent $event): void{
		$p = $event->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		$arena->getEventListener()->onPlayerExhaust($event);
	}

	/**
	 * @param PlayerChatEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerChatEvent(PlayerChatEvent $event): void{
		$p = $event->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		$arena->getEventListener()->onPlayerChatEvent($event);
	}

	public function onInventoryCloseEvent(InventoryCloseEvent $event): void{
		$p = $event->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		$arena->getEventListener()->onInventoryCloseEvent($event);
	}

	public function onItemPickupEvent(InventoryPickupItemEvent $event): void{
		/** @var PlayerInventory $inv */
		$inv = $event->getInventory();
		/** @var Player $p */
		$p = $inv->getHolder();

		$arena = $this->getArena($p);
		if($arena === null) return;

		if($arena->getEventListener()->onItemPickupEvent($p, $event->getItem()->getItem())){
			$event->setCancelled();
		}
	}

	public function onItemDropEvent(PlayerDropItemEvent $event): void{
		$p = $event->getPlayer();

		$arena = $this->getArena($p);
		if($arena === null) return;

		if($arena->getEventListener()->onItemDropEvent($p, $event->getItem())){
			$event->setCancelled();
		}
	}

	/**
	 * Handles player damages towards another players. This event is to log player's damages towards
	 * another player entity. This is required to check who actually killed this player.
	 *
	 * @param EntityDamageEvent $event
	 * @priority HIGHEST
	 */
	public function onHit(EntityDamageEvent $event): void{
		$entity = $event->getEntity();

		$player = $entity instanceof Player ? $entity : null;
		if($player === null || ($arena = $this->getArena($player)) === null){
			return;
		}

		// Cancel any events that are not related to state running.
		if($arena->getStatus() != ArenaState::STATE_ARENA_RUNNING){
			$event->setCancelled(true);

			return;
		}

		$arena->getEventListener()->onPlayerHitEvent($event);
	}

	/**
	 * @param PlayerKickEvent $event
	 * @priority NORMAL
	 */
	public function playerKickEvent(PlayerKickEvent $event): void{
		$arena = $this->getArena($event->getPlayer());
		if($arena === null) return;

		$arena->leaveArena($event->getPlayer(), true);
	}

	/**
	 * Handles player commands thru a severe permissions checks.
	 * This prevents the player from using a command that is forbidden to this game.
	 *
	 * @param PlayerCommandPreprocessEvent $ev
	 * @priority NORMAL
	 */
	public function onCommand(PlayerCommandPreprocessEvent $ev): void{
		$arena = $this->getArena($ev->getPlayer());
		if($arena === null) return;

		$cmd = strtolower($ev->getMessage());
		if($cmd[0] === '/'){
			$arena->getEventListener()->onPlayerExecuteCommand($ev);
		}
	}

	/**
	 * Handles player interaction with the arena signs.
	 *
	 * @param PlayerInteractEvent $e
	 * @priority NORMAL
	 */
	public function onPlayerInteract(PlayerInteractEvent $e): void{
		$p = $e->getPlayer();

		if(($arena = $this->getArena($e->getPlayer())) !== null){
			$item = $p->getInventory()->getItemInHand();

			// Unsafe method, hopefully the code will prevent these?
			if($item->equals(Arena::getLeaveItem())){
				$arena->leaveArena($p);

				$e->setCancelled();
			}elseif($item->equals(Arena::getKitSelector())){
				$arena->onKitSelection($p);

				$e->setCancelled();
			}elseif($item->equals(Arena::getSpectatorItem())){
				$arena->onSpectatorSelection($p);

				$e->setCancelled();
			}elseif($item->equals(Arena::getRejoinItem())){
				$arena->onRejoinSelection($p);

				$e->setCancelled();
			}


			if(!$e->isCancelled() && $arena->getEventListener()->onPlayerInteractEvent($e)){
				$e->setCancelled();
			}
		}
	}

}