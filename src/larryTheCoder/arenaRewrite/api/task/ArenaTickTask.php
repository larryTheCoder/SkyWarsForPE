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

namespace larryTheCoder\arenaRewrite\api\task;


use larryTheCoder\arenaRewrite\api\Arena;
use larryTheCoder\arenaRewrite\api\impl\ArenaState;
use larryTheCoder\arenaRewrite\api\impl\ShutdownSequence;
use pocketmine\level\sound\ClickSound;
use pocketmine\scheduler\Task;

/**
 * This class implicitly calls for arena tasking function, for instance, waiting task, in game tasks and overtime tasks.
 * The task being separated from the arena core make sure that each event will be called exactly on the moment, and of course
 * reducing the amount of debugging needed to test the code.
 *
 * <p> This code documentation are still to be written.
 *
 * @package larryTheCoder\arenaRewrite\api\task
 */
abstract class ArenaTickTask extends Task implements ShutdownSequence {

	/** @var int */
	protected $timeElapsed = 0;

	/** @var Arena */
	private $arena;

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	final public function onRun(int $currentTick): void{
		$arena = $this->getArena();

		$state = $arena->getStatus();
		$pm = $arena->getPlayerManager();

		$arena->processQueue();
		$arena->getSignManager()->processSign();

		// Do not perform anything when the world is offline
		if($arena->hasFlags(Arena::ARENA_OFFLINE_MODE)){
			return;
		}

		$arena->checkAlive();

		switch($state){
			case ArenaState::STATE_WAITING:
				if(count($pm->getAlivePlayers()) >= $arena->getMinPlayer()){
					$pm->broadcastToPlayers("Game starting in 30 seconds.");

					$arena->setStatus(ArenaState::STATE_STARTING);
				}

				$this->timeElapsed = 0;
				break;
			case ArenaState::STATE_STARTING:
				if(count($pm->getAlivePlayers()) < $arena->getMinPlayer()){
					$pm->broadcastTitle("Not enough players, countdown cancelled.");

					$arena->setStatus(ArenaState::STATE_WAITING);
					break;
				}

				// Game starting title.
				if($this->timeElapsed === 20){
					$pm->broadcastTitle("Starting in", "", 1, 25, 1);
				}elseif($this->timeElapsed > 20){
					foreach($pm->getAllPlayers() as $player){
						$player->getLevel()->addSound((new ClickSound($player)), [$player]);
					}

					if($this->timeElapsed < 29){
						$pm->broadcastTitle("§6" . (30 - $this->timeElapsed), "", 1, 25, 1);
					}else{
						$pm->broadcastTitle("§c" . (30 - $this->timeElapsed), "", 1, 25, 1);
					}
				}

				// Preparation unit and so on.
				if($this->timeElapsed === 25){
					$arena->loadWorld(false);
				}elseif($this->timeElapsed === 30){
					$this->reset();

					$arena->startArena();
					$arena->setStatus(ArenaState::STATE_ARENA_RUNNING);
				}

				$this->timeElapsed++;
				break;
			case ArenaState::STATE_ARENA_RUNNING:
				if($this->timeElapsed < $this->getMaxTime()){
					$this->gameTick();
				}elseif($this->timeElapsed >= $this->getMaxTime()){
					$this->overtimeTick();
				}

				$this->timeElapsed++;
				break;
			case ArenaState::STATE_ARENA_CELEBRATING:
				$this->endTick();

				$this->timeElapsed++;
				break;
		}

		if($state === ArenaState::STATE_WAITING || $state === ArenaState::STATE_STARTING){
			$this->tickPreScoreboard();
		}else{
			$this->tickGameScoreboard();
		}
	}

	public function getArena(): Arena{
		return $this->arena;
	}

	/**
	 * Reset the arena task state to its original condition
	 */
	public function reset(): void{
		$this->timeElapsed = 0;
	}

	/**
	 * The maximum time for an arena to tick, in unit of seconds.
	 *
	 * @return int
	 */
	public abstract function getMaxTime(): int;

	/**
	 * This function is called when the arena is running, perform appropriate tasks
	 * in this function.
	 */
	public abstract function gameTick(): void;

	/**
	 * This function however will be called when the arena reached its maximum arena tick as defined in
	 * {@link ArenaTickTask::getMaxTime()}.
	 */
	public function overtimeTick(): void{
		// NOOP
	}

	/**
	 * This function will be called when the arena state has changed to {@link ArenaState::STATE_ARENA_CELEBRATING}
	 */
	public function endTick(): void{
		$this->getArena()->stopArena();
		$this->getArena()->resetArena();
	}

	public function tickPreScoreboard(): void{

	}

	/**
	 * Perform scoreboard actions on this function, only be called when the arena has started.
	 */
	public function tickGameScoreboard(): void{
		// NOOP
	}

	public function shutdown(): void{
		$this->getHandler()->cancel();
	}
}