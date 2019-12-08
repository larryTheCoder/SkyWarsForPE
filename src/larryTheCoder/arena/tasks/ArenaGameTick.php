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

namespace larryTheCoder\arena\tasks;

use larryTheCoder\arena\api\DefaultGameAPI;
use larryTheCoder\arena\Arena;
use larryTheCoder\arena\State;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use pocketmine\command\CommandSender;
use pocketmine\level\sound\ClickSound;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class ArenaGameTick extends Task {

	/**@var Arena */
	private $arena;
	/** @var DefaultGameAPI */
	private $gameAPI;

	/** @var int */
	private $startTime = 0;
	/** @var int */
	private $arenaTicks = 0;

	public function __construct(Arena $arena, DefaultGameAPI $gameAPI){
		$this->arena = $arena;
		$this->gameAPI = $gameAPI;

		$this->gameAPI->fallTime = $arena->arenaGraceTime;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		// Uwu u found me, now tell myself that I need to finish my code.
		$this->arenaTicks++;
		$this->checkLevelTime();
		$this->gameAPI->statusUpdate();
		switch($this->arena->getStatus()){
			case State::STATE_WAITING:
				// Nothing interesting in this state yet...
				// Just a few things to check if the player is starting or not...
				if(empty($this->arena->getPlayersCount()) || $this->arena->getPlayersCount() < $this->arena->minimumPlayers){
					foreach($this->arena->getPlayers() as $p) $p->sendPopup($this->getMessage($p, "arena-wait-players", false));

					$this->startTime = 60;
					break;
				}

				$this->arena->setStatus(State::STATE_SLOPE_WAITING);
				break;
			case State::STATE_SLOPE_WAITING:
				// Check if there is any sufficient plays in the arena, otherwise reverse back
				// to STATE_WAITING status.
				if(empty($this->arena->getPlayersCount()) || $this->arena->getPlayersCount() < $this->arena->minimumPlayers){
					foreach($this->arena->getPlayers() as $p){
						$p->sendPopup($this->getMessage($p, "arena-low-players", false));
					}

					$this->startTime = 60;
					break;
				}
				$this->startTime--;

				foreach($this->arena->getPlayers() as $p){
					if($p instanceof Player){
						$p->setXpLevel($this->startTime);
					}

					if($this->startTime <= 11){
						$p->getLevel()->addSound((new ClickSound($p)), [$p]);
						if($this->startTime === 11){
							$p->addTitle($this->getMessage($p, 'arena-starting', false));
						}elseif($this->startTime <= 3){
							$p->addSubTitle($this->getMessage($p, 'arena-subtitle', false));
							if($this->startTime > 1){
								$p->addTitle("§6" . $this->startTime);
							}else{
								$p->addTitle("§c" . $this->startTime);
							}
						}else{
							$p->addTitle("§a" . $this->startTime);
						}
					}
				}

				if($this->startTime == 0){
					$this->arena->startGame();
					$this->startTime = 60;
					break;
				}

				if(Settings::$startWhenFull && $this->arena->maximumPlayers <= $this->arena->getPlayersCount()){
					$this->arena->startGame();
					$this->startTime = 60;
				}
				break;
			case State::STATE_ARENA_RUNNING:
				if($this->gameAPI->fallTime !== 0){
					$this->gameAPI->fallTime--;
				}

				// TODO: Write arena running state.
				break;
		}

		foreach($this->arena->getPlayers() as $pl){
			$this->gameAPI->scoreboard->updateScoreboard($pl);
		}
	}

	public function getMessage(?CommandSender $p, $key, $prefix = true){
		return SkyWarsPE::getInstance()->getMsg($p, $key, $prefix);
	}

	private function useScoreboard(){

	}

	private function tickBossBar(Player $p, int $id, $data = null){
		// TODO: Boss bar feature.
	}

	private function checkLevelTime(){
		$tickTime = $this->arena->arenaTime;
		if(!$tickTime){
			return;
		}

		$this->arena->getLevel()->setTime($tickTime);
		$this->arena->getLevel()->stopTime();
	}
}