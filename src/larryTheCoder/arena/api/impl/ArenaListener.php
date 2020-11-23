<?php
/*
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

namespace larryTheCoder\arena\api\impl;

use larryTheCoder\arena\api\Arena;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\Player;

/**
 * Arena listener class, you may want to extends this class in order to work properly.
 * This package is considered to be used within the game API. In the other hand, all other
 * internal listener will be handled in other class.
 */
class ArenaListener {

	/** @var Arena */
	protected $arena;

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	public function onPlayerMove(PlayerMoveEvent $event): void{
		// NOOP
	}

	public function onBlockPlaceEvent(BlockPlaceEvent $event): void{
		// NOOP
	}

	public function onBlockBreakEvent(BlockBreakEvent $event): void{
		// NOOP
	}

	public function onPlayerHitEvent(EntityDamageEvent $event): void{
		// NOOP
	}

	public function onPlayerQuitEvent(PlayerQuitEvent $event): void{
		// NOOP
	}

	public function onPlayerExhaust(PlayerExhaustEvent $event): void{
		// NOOP
	}

	public function onPlayerChatEvent(PlayerChatEvent $event): void{
		// NOOP
	}

	/**
	 * Handles item drop event from a player, this event will be cancelled if the
	 * function returns true.
	 *
	 * @param Player $player
	 * @param Item $item
	 * @return bool
	 */
	public function onItemDropEvent(Player $player, Item $item): bool{
		$pm = $this->arena->getPlayerManager();

		return $pm->isSpectator($player) || $this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING;
	}

	/**
	 * Handles item pickup event from a player, this event is as the same as {@link ArenaListener::onItemDropEvent()}.
	 * However, please make sure that this function will be called in the end of your code if you override this method.
	 *
	 * @param Player $player
	 * @param Item $item
	 * @return bool
	 */
	public function onItemPickupEvent(Player $player, Item $item): bool{
		$pm = $this->arena->getPlayerManager();

		return $pm->isSpectator($player) || $this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING;
	}

	public function onInventoryOpenEvent(InventoryOpenEvent $event): void{
		// NOOP
	}

	public function onInventoryCloseEvent(InventoryCloseEvent $event): void{
		// NOOP
	}

	public function onPlayerDeath(Player $player, int $deathFrom = EntityDamageEvent::CAUSE_SUICIDE): void{
		// NOOP
	}

	public function onPlayerExecuteCommand(PlayerCommandPreprocessEvent $ev): void{
		// NOOP
	}

	public function onPlayerInteractEvent(PlayerInteractEvent $e): bool{
		$pm = $this->arena->getPlayerManager();

		return $pm->isSpectator($e->getPlayer()) || $this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING;
	}
}