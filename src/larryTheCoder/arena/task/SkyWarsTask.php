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
		}elseif($this->timeElapsed === 15){
			$this->getArena()->setFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD, false);

			$pm->broadcastToPlayers(TextFormat::RED . "You are no longer invincible.", false);
		}elseif($this->timeElapsed % $this->getRefillTime()){
			$this->getArena()->refillChests();
		}
	}

	public function overtimeTick(): void{
		$arena = $this->getArena();
		$pm = $arena->getPlayerManager();
		if(count($pm->getAlivePlayers()) === 0){
			$this->endTick();

			return;
		}

		$winners = $pm->getWinners();
		if($this->timeElapsed === 0){
			foreach($pm->getAlivePlayers() as $player){
				$player->sendMessage("Congratulations! You have won the match.");

				$this->getArena()->unsetPlayer($player);
			}
		}elseif($this->timeElapsed === 5){
			$this->endTick();

			// Execute various commands, this will be ran outside arena match.
			foreach($winners as $rank => $winner){
				$command = $this->getArena()->winnersCommand[$rank];
				if(!is_array($command)){
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $command);
				}else{
					foreach($command as $cmd){
						Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $cmd);
					}
				}
			}
		}
	}

	private function getRefillTime(bool $reset = false){
		if($this->nextRefill === -1 || $reset){
			return $this->nextRefill = $this->refillAverage[array_rand($this->refillAverage)];
		}else{
			return $this->nextRefill;
		}
	}

	/**
	 * @return ArenaImpl|Arena
	 */
	public function getArena(): Arena{
		return parent::getArena();
	}
}