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


use larryTheCoder\arenaRewrite\api\impl\ArenaListener;
use larryTheCoder\arenaRewrite\api\impl\ArenaState;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EventListener implements ArenaListener {

	/** @var ArenaImpl */
	private $arena;

	public function __construct(ArenaImpl $arena){
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

	public function onPlayerInteractEvent(PlayerInteractEvent $e): void{
		// NOOP
	}

	public function onPlayerQuitEvent(PlayerQuitEvent $event): void{
		$player = $event->getPlayer();

		$this->arena->leaveArena($player, true);
	}

	public function onPlayerHitEvent(EntityDamageEvent $event): void{
		if($this->arena->hasFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD)){
			$event->setCancelled();
		}
	}

	public function onPlayerDeath(Player $player, $deathFrom): void{
		Utils::strikeLightning($player);

		$pm = $this->arena->getPlayerManager();
		$this->arena->playerSpectate($player);
		$pm->setSpectator($player);

		$player->setGamemode(Player::ADVENTURE);
		$player->setAllowFlight(true);
		$player->sendMessage(SkyWarsPE::getInstance()->getMsg($player, 'player-spectate'));

		foreach($pm->getAllPlayers() as $p2) $p2->hidePlayer($player);

		$player->teleport(Position::fromObject($this->arena->arenaSpecPos, $this->arena->getLevel()));
	}

	public function onPlayerExecuteCommand(PlayerCommandPreprocessEvent $event): void{
		$player = $event->getPlayer();

		if(!in_array(strtolower($event->getMessage()), ['sw', 'skywars'], true)
			&& $this->arena->getStatus() === ArenaState::STATE_ARENA_RUNNING
			&& !$player->hasPermission("sw.command.bypass")){
			$player->sendMessage(TextFormat::RED . "You cannot execute any command while in game.");
		}
	}
}