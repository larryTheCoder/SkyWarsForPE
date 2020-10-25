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

namespace larryTheCoder\arenaRewrite;


use larryTheCoder\arenaRewrite\api\Arena;
use larryTheCoder\arenaRewrite\api\impl\ArenaListener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\Player;

class EventListener implements ArenaListener {

	/**
	 * @var Arena
	 */
	private $arena;

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
		if($this->arena->hasFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD)){
			$event->setCancelled();
		}
	}

	public function onPlayerDeath(Player $targetPlayer, $deathFrom): void{
		// TODO: Implement onPlayerDeath() method.
	}

	public function onPlayerExecuteCommand(PlayerCommandPreprocessEvent $ev): void{
		// TODO: Implement onPlayerExecuteCommand() method.
	}

	public function onPlayerInteractEvent(PlayerInteractEvent $e): void{
		// TODO: Implement onPlayerInteractEvent() method.
	}
}