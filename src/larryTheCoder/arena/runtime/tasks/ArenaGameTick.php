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

declare(strict_types = 1);

namespace larryTheCoder\arena\runtime\tasks;

use larryTheCoder\arena\api\ArenaState;
use larryTheCoder\arena\api\ArenaTask;
use larryTheCoder\arena\Arena;
use larryTheCoder\arena\runtime\DefaultGameAPI;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\task\ParticleTask;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\level\sound\ClickSound;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\tile\Chest;
use pocketmine\utils\MainLogger;

class ArenaGameTick extends Task implements ArenaTask {

	/** @var int */
	private $errorDepth = 0;

	/**@var Arena */
	private $arena;
	/** @var DefaultGameAPI */
	private $gameAPI;

	/** @var int */
	private $startTime = 0;
	/** @var int */
	private $arenaTicks = 0;
	/** @var int */
	private $refillCountdown;
	/** @var int */
	private $endTime;

	public function __construct(Arena $arena, DefaultGameAPI $gameAPI){
		$this->arena = $arena;
		$this->gameAPI = $gameAPI;

		$this->arena->fallTime = $arena->arenaGraceTime;

		$refillAvg = $this->arena->refillAverage;
		$this->refillCountdown = $refillAvg[array_rand($refillAvg)];
	}

	public function getName(): string{
		return "Arena Main Scheduling Task";
	}

	public function onRun(int $currentTick): void{
		try{
			$this->arenaTicks++; // Uwu u found me, now tell myself that I need to finish my code.

			$this->checkLevelTime();
			$this->gameAPI->statusUpdate();
			switch($this->arena->getStatus()){
				case ArenaState::STATE_WAITING:
					// Nothing interesting in this state yet...
					// Just a few things to check if the player is starting or not...
					if(empty($this->arena->getPlayersCount()) || $this->arena->getPlayersCount() < $this->arena->minimumPlayers){
						$this->arena->broadcastToPlayers("arena-wait-players", true);

						$this->gameAPI->scoreboard->setCurrentEvent("§6Waiting for players");

						$this->startTime = $this->arena->arenaStartingTime;
						break;
					}

					$this->arena->setStatus(ArenaState::STATE_SLOPE_WAITING);
					break;
				case ArenaState::STATE_SLOPE_WAITING:
					// Check if there is any sufficient plays in the arena, otherwise reverse back
					// to STATE_WAITING status.
					if(empty($this->arena->getPlayersCount()) || $this->arena->getPlayersCount() < $this->arena->minimumPlayers){
						$this->arena->broadcastToPlayers("arena-low-players", true);
						$this->gameAPI->scoreboard->setCurrentEvent("§cNot enough players");

						$this->startTime = $this->arena->arenaStartingTime;

						$this->arena->setStatus(ArenaState::STATE_WAITING);
						break;
					}
					$this->startTime--;
					if($this->startTime <= 3 && $this->startTime > 1){
						$this->gameAPI->scoreboard->setCurrentEvent("Starting in §6" . $this->startTime);
					}elseif($this->startTime <= 1){
						$this->gameAPI->scoreboard->setCurrentEvent("Starting in §c" . $this->startTime);
					}else{
						$this->gameAPI->scoreboard->setCurrentEvent("Starting in §a" . $this->startTime);
					}

					foreach($this->arena->getPlayers() as $p){
						if($p instanceof Player){
							$p->setXpLevel($this->startTime);
						}

						if($this->startTime <= 11){
							$p->getLevel()->addSound((new ClickSound($p)), [$p]);
							$p->setTitleDuration(1, 25, 1);

							if($this->startTime === 11){
								$p->sendTitle($this->getMessage($p, 'arena-starting', false));
							}elseif($this->startTime <= 3){
								$p->sendSubTitle($this->getMessage($p, 'arena-subtitle', false));
								if($this->startTime > 1){
									$p->sendTitle("§6" . $this->startTime);
								}else{
									$p->sendTitle("§c" . $this->startTime);
								}
							}else{
								$p->sendTitle("§a" . $this->startTime);
							}
						}
					}

					if($this->startTime == 0){
						$this->arena->startGame();
						$this->startTime = $this->arena->arenaStartingTime;
						break;
					}

					if(Settings::$startWhenFull && $this->arena->maximumPlayers <= $this->arena->getPlayersCount()){
						$this->arena->startGame();
						$this->startTime = $this->arena->arenaStartingTime;
					}
					break;
				case ArenaState::STATE_ARENA_RUNNING:
					if($this->arena->fallTime !== 0) $this->arena->fallTime--;

					// Chest refill and such...
					if($this->refillCountdown <= 0 && $this->arena->refillChest){
						$this->gameAPI->refillChests();

						$refillAvg = $this->arena->refillAverage;
						$this->refillCountdown = $refillAvg[array_rand($refillAvg)];

						foreach($this->arena->getLevel()->getTiles() as $tiles){
							if($tiles instanceof Chest){
								$task = new ParticleTask($tiles);
								SkyWarsPE::getInstance()->getScheduler()->scheduleRepeatingTask($task, 1);
							}
						}
					}

					break;
				case ArenaState::STATE_ARENA_CELEBRATING:
					if($this->endTime === 0){
						$this->gameAPI->broadcastResult();
					}

					if(empty($this->arena->getPlayers())){
						$this->arena->stopGame();
						$this->endTime = 0;

						break;
					}

					foreach($this->arena->getPlayers() as $player){
						$facing = $player->getDirection();
						$vec = $player->getSide($facing, -3);
						if($this->endTime <= 5){
							Utils::addFireworks($vec);
						}
					}

					if($this->endTime > 10){
						$this->arena->stopGame();
						$this->endTime = 0;
					}

					$this->endTime++;
					break;
			}

			$this->arena->checkAlive();
			foreach($this->arena->getAllPlayers() as $pl){
				$this->gameAPI->scoreboard->updateScoreboard($pl);
			}

			$this->errorDepth--;
		}catch(\Throwable $error){
			$this->errorDepth++;

			$this->gameAPI->getDebugger()->logException($error);
			if($this->errorDepth < 5){
				MainLogger::getLogger()->logException($error);
			}else{
				throw new \RuntimeException("Repeated amount of trace logs has been detected", 0, $error);
			}
		}
	}

	public function getMessage(?CommandSender $p, $key, $prefix = true): string{
		return SkyWarsPE::getInstance()->getMsg($p, $key, $prefix);
	}

	private function checkLevelTime(): void{
		$tickTime = (int)$this->arena->arenaTime;
		if(!$tickTime){
			return;
		}

		$level = $this->arena->getLevel();
		if($level === null) return;

		$level->setTime($tickTime);
		$level->stopTime();
	}

	public function shutdown(): void{
		$this->gameAPI->getDebugger()->log("Shutting down ArenaGameTick.");

		$this->getHandler()->cancel();
	}
}