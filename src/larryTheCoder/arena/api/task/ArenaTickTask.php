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

namespace larryTheCoder\arena\api\task;


use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\impl\ShutdownSequence;
use larryTheCoder\utils\Utils;
use pocketmine\scheduler\Task;
use pocketmine\utils\MainLogger;
use pocketmine\utils\TextFormat;
use Throwable;

/**
 * This class implicitly calls for arena tasking function, for instance, waiting task, in game tasks and overtime tasks.
 * The task being separated from the arena core make sure that each event will be called exactly on the moment, and of course
 * reducing the amount of debugging needed to test the code.
 *
 * <p> This code documentation are still to be written.
 */
abstract class ArenaTickTask extends Task implements ShutdownSequence {

	/** @var int */
	protected $timeElapsed = 0;
	/** @var int */
	protected $countdown = 30;

	/** @var Arena */
	private $arena;
	/** @var bool */
	private $hasEnded = false;

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	final public function onRun(int $currentTick): void{
		$this->getArena()->getSignManager()->processSign();

		// Arena has encountered an unrecoverable error.
		if($this->getArena()->hasFlags(Arena::ARENA_CRASHED) || $this->getArena()->hasFlags(Arena::ARENA_DISABLED)){
			return;
		}

		try{
			$this->run();
		}catch(Throwable $err){
			$this->getArena()->setFlags(Arena::ARENA_CRASHED, true);
			$this->endTick();

			MainLogger::getLogger()->logException($err);
		}
	}

	private function run(): void{
		$arena = $this->getArena();

		// Do nothing since the arena is in setup mode.
		if($arena->hasFlags(Arena::ARENA_IN_SETUP_MODE)){
			return;
		}

		$state = $arena->getStatus();
		$pm = $arena->getPlayerManager();

		$arena->processQueue();

		// Do not perform anything when the world is offline
		if($arena->hasFlags(Arena::ARENA_OFFLINE_MODE)){
			return;
		}

		if($state === ArenaState::STATE_WAITING || $state === ArenaState::STATE_STARTING){
			$this->tickPreScoreboard();
		}else{
			$this->tickGameScoreboard();
		}

		switch($state){
			case ArenaState::STATE_WAITING:
				if(count($pm->getAlivePlayers()) >= $arena->getMinPlayer()){
					$pm->broadcastToPlayers('arena-startup', false, ["{TIME}" => $this->countdown]);

					$arena->setStatus(ArenaState::STATE_STARTING);
				}

				$this->reset();
				break;
			case ArenaState::STATE_STARTING:
				if(count($pm->getAlivePlayers()) < $arena->getMinPlayer()){
					$pm->broadcastTitle(TextFormat::RED . "Not enough players", TextFormat::RED . "Countdown has been cancelled.");

					$arena->setStatus(ArenaState::STATE_WAITING);
					break;
				}

				// Game starting title.
				if($this->countdown === 10){
					$pm->broadcastTitle('countdown-starting-title', "countdown-starting-subtitle", 1, 25, 1);
				}elseif($this->countdown < 10){
					if($this->countdown > 3){
						Utils::addSound($pm->getAllPlayers(), "random.click");

						$pm->broadcastTitle("§6" . $this->countdown, "", 1, 25, 1);
					}else{
						if($this->countdown === 0){
							Utils::addSound($pm->getAlivePlayers(), "note.bell");

							$pm->broadcastTitle("countdown-started-title", "countdown-started-subtitle", 1, 25, 1);

							$arena->getScoreboard()->setStatus(TextFormat::GREEN . "Match started!");
						}else{
							Utils::addSound($pm->getAllPlayers(), "random.click");

							$pm->broadcastTitle("§c" . $this->countdown, "", 1, 25, 1);
						}
					}
				}

				// Preparation unit and so on.
				if($this->countdown === 5){
					$arena->loadWorld(false);
				}elseif($this->countdown === 0){
					$this->reset();

					$arena->startArena();
					$arena->setStatus(ArenaState::STATE_ARENA_RUNNING);

					return;
				}

				$this->countdown--;
				break;
			case ArenaState::STATE_ARENA_RUNNING:
				$arena->checkAlive();

				if($this->timeElapsed < $this->getMaxTime()){
					$this->gameTick();
				}elseif($this->timeElapsed >= $this->getMaxTime()){
					$this->overtimeTick();
				}

				$pm->updateWinners();

				$this->timeElapsed++;
				break;
			case ArenaState::STATE_ARENA_CELEBRATING:
				if(!$this->hasEnded && $this->timeElapsed > 0){
					$this->hasEnded = true;
					$this->timeElapsed = 0;
				}

				$this->endTick();

				$this->timeElapsed++;
				break;
		}

		$this->getArena()->getScoreboard()->tickScoreboard();
	}

	public function getArena(): Arena{
		return $this->arena;
	}

	/**
	 * Reset the arena task state to its original condition
	 */
	public function reset(): void{
		$this->timeElapsed = 0;
		$this->hasEnded = false;
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
		$this->getArena()->setStatus(ArenaState::STATE_ARENA_CELEBRATING);
	}

	/**
	 * This function will be called when the arena state has changed to {@link ArenaState::STATE_ARENA_CELEBRATING}
	 */
	public function endTick(): void{
		$this->reset();

		$this->getArena()->stopArena();
		$this->getArena()->resetArena();
	}

	public function tickPreScoreboard(): void{
		$arena = $this->getArena();
		switch($arena->getStatus()){
			case ArenaState::STATE_WAITING:
				$arena->getScoreboard()->setStatus(TextFormat::GREEN . "Waiting...");
				break;
			case ArenaState::STATE_STARTING:
				$arena->getScoreboard()->setStatus(TextFormat::YELLOW . "Starting in " . $this->countdown . "s");
				break;
			default:
				$arena->getScoreboard()->setStatus(TextFormat::YELLOW . "N/A");
				break;
		}
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