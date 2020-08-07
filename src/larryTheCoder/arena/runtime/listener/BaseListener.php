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

namespace larryTheCoder\arena\runtime\listener;

use larryTheCoder\arena\api\ArenaListener;
use larryTheCoder\arena\api\ArenaState;
use larryTheCoder\arena\Arena;
use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\Player;

class BaseListener implements ArenaListener {

	/** @var Arena */
	private $arena;

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	public function onPlayerMove(PlayerMoveEvent $event): void{
	}

	public function onBlockPlaceEvent(BlockPlaceEvent $event): void{
	}

	public function onBlockBreakEvent(BlockBreakEvent $event): void{
	}

	public function onPlayerHitEvent(EntityDamageEvent $event): void{
	}

	public function onPlayerDeath(Player $targetPlayer, $deathFrom): void{
		SkyWarsPE::getInstance()->getDatabase()->getPlayerData($targetPlayer->getName(), function(PlayerData $pd) use ($targetPlayer){
			$pd->death++;
			$pd->lost++;
			$pd->kill += $this->arena->kills[$targetPlayer->getName()] ?? 0;
			$pd->time += (microtime(true) - $this->arena->startedTime);

			SkyWarsPE::$instance->getDatabase()->setPlayerData($targetPlayer->getName(), $pd);
		});

		$this->arena->getDebugger()->log("[Arena]: {$targetPlayer->getName()} is knocked out in the game");

		if($this->arena->enableSpectator){
			$this->arena->setSpectator($targetPlayer);

			$targetPlayer->setGamemode(Player::SPECTATOR);
			$targetPlayer->sendMessage(SkyWarsPE::getInstance()->getMsg($targetPlayer, 'player-spectate'));
			$this->arena->gameAPI->giveGameItems($targetPlayer, true);

			foreach($this->arena->getPlayers() as $p2) $p2->hidePlayer($targetPlayer);

			$targetPlayer->teleport(Position::fromObject($this->arena->arenaSpecPos, $this->arena->getLevel()));

			return;
		}
		$this->arena->leaveArena($targetPlayer);
	}

	public function onPlayerExecuteCommand(PlayerCommandPreprocessEvent $ev): void{
		$p = $ev->getPlayer();
		$cmd = strtolower($ev->getMessage());

		$cmd = explode(' ', $cmd)[0];
		// In arena, no permission, is alive, arena started === cannot use command.
		$val = $this->arena->isInArena($p)
			&& !$p->hasPermission("sw.admin.bypass")
			&& $this->arena->getPlayerState($p) === ArenaState::PLAYER_ALIVE
			&& $this->arena->getStatus() === ArenaState::STATE_ARENA_RUNNING;
		if($val){
			if(!in_array($cmd, Settings::$acceptedCommand, true) && $cmd !== "sw"){
				$ev->getPlayer()->sendMessage(SkyWarsPE::getInstance()->getMsg($p, "banned-command"));
				$ev->setCancelled(true);
			}
		}
	}
}