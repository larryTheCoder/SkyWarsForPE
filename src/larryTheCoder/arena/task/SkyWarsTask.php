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

namespace larryTheCoder\arena\task;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\task\ArenaTickTask;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\utils\Utils;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class SkyWarsTask extends ArenaTickTask {

	/** @var int[] */
	private $refillAverage;
	/** @var int */
	private $nextRefill = -1;

	public function __construct(ArenaImpl $arena){
		parent::__construct($arena);

		$this->refillAverage = $arena->refillAverage;
	}

	public function getMaxTime(): int{
		return $this->getArena()->arenaMatchTime;
	}

	public function gameTick(): void{
		$pm = $this->getArena()->getPlayerManager();

		if($this->timeElapsed === 0){
			$this->getArena()->refillChests();
		}elseif($this->timeElapsed === $this->getArena()->arenaGraceTime){
			$this->getArena()->setFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD, false);

			$pm->broadcastToPlayers('arena-no-invincible');
		}elseif($this->timeElapsed % $this->getRefillTime() === 0){
			$this->getArena()->refillChests();

			$pm->broadcastToPlayers('arena-chest-refilled');
		}
	}

	public function endTick(): void{
		$arena = $this->getArena();
		$pm = $arena->getPlayerManager();

		$winners = $pm->getWinners();
		if($this->timeElapsed === 0){
			Utils::addSound($pm->getAllPlayers(), "random.levelup");

			foreach($pm->getAlivePlayers() as $player){
				SkyWarsDatabase::addWins($player->getName());

				$player->sendMessage(TextFormat::GREEN . "Congratulations! You have won the match.");

				$arena->unsetPlayer($player);

				$player->getInventory()->setItem(4, Arena::getRejoinItem());
				$player->getInventory()->setItem(8, Arena::getLeaveItem());
			}
		}elseif($this->timeElapsed === 5){
			// Execute various commands, this will be ran outside arena match.
			$level = 1;
			$cached = [];
			foreach($winners as $rank => [$playerName, $kills]){
				if($playerName !== "N/A"){
					$command = $arena->winnersCommand[$rank] ?? [];
					foreach($command as $cmd){
						Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), str_replace("%p", "\"" . $playerName . "\"", $cmd));
					}
				}

				// Only allows 3 player in top killers
				if($level <= 3){
					$cached[] = TextFormat::GOLD . " $level §c-§e $playerName (§c $kills kills §e)";
					$level++;
				}
			}

			$pm->broadcastToPlayers(TextFormat::GRAY . TextFormat::BOLD . "-----------------------");
			$pm->broadcastToPlayers(TextFormat::GREEN . TextFormat::BOLD . " TOP KILLERS: ");
			$pm->broadcastToPlayers(TextFormat::GRAY . TextFormat::BOLD . "");

			foreach($cached as $cache){
				$pm->broadcastToPlayers($cache);
			}

			$pm->broadcastToPlayers(TextFormat::GRAY . TextFormat::BOLD . "-----------------------");
		}elseif($this->timeElapsed === 10){
			parent::endTick();
		}
	}

	public function tickGameScoreboard(): void{
		$arena = $this->getArena();
		if($arena->hasFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD)){
			$arena->getScoreboard()->setStatus(TextFormat::RED . "Invincible for " . ($arena->arenaGraceTime - $this->timeElapsed) . "s");
		}elseif($this->nextRefill - ($this->timeElapsed % $this->nextRefill) <= 30){
			$arena->getScoreboard()->setStatus(TextFormat::GREEN . "Chest refill in " . ($this->nextRefill - ($this->timeElapsed % $this->nextRefill)) . "s");
		}else{
			$arena->getScoreboard()->setStatus(TextFormat::RED . "Game ends in " . date('i:s', $this->getMaxTime() - $this->timeElapsed));
		}
	}

	private function getRefillTime(bool $reset = false): int{
		if($this->nextRefill === -1 || $reset){
			return $this->nextRefill = $this->refillAverage[array_rand($this->refillAverage)];
		}else{
			return $this->nextRefill;
		}
	}

	public function reset(): void{
		parent::reset();

		$this->countdown = $this->getArena()->arenaStartingTime;
	}

	/**
	 * @return ArenaImpl
	 */
	public function getArena(): Arena{
		/** @var ArenaImpl $arena */
		$arena = parent::getArena();

		return $arena;
	}
}