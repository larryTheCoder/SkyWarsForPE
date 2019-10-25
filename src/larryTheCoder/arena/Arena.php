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

namespace larryTheCoder\arena;

use larryTheCoder\arena\api\DefaultGameAPI;
use larryTheCoder\arena\api\GameAPI;
use larryTheCoder\arena\tasks\PlayerDeathTask;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Config;

/**
 * Presenting the arena of the SkyWars.
 * Improved and rewrites old arena code.
 *
 * @package larryTheCoder\arenaRewrite
 */
class Arena {
	use PlayerHandler;
	use ArenaData;

	/*** @var SkyWarsPE */
	private $plugin;
	/** @var int */
	private $arenaStatus = State::STATE_WAITING;

	/** @var GameAPI */
	public $gameAPI;
	/** @var array */
	public $data;
	/** @var float */
	public $startedTime = 0;

	/** @var Vector3[] */
	private $freePedestals;
	/** @var Vector3[] */
	public $usedPedestals;

	/** @var TaskHandler[] */
	private $taskRunning = [];

	public function __construct(string $arenaName, SkyWarsPE $plugin){
		$this->arenaName = $arenaName;
		$this->plugin = $plugin;

		$this->resetArena(true);
	}

	/**
	 * Start this arena and set the arena state.
	 */
	public function startGame(){
		$this->gameAPI->startArena();

		$this->startedTime = microtime(true);
		$this->setStatus(State::STATE_ARENA_RUNNING);
		$this->messageArenaPlayers('arena-start', false);
	}

	/**
	 * Stop this arena and set the arena state.
	 */
	public function stopGame(){
		$this->gameAPI->stopArena();

		$this->resetArena();
	}

	/**
	 * @return Level|null
	 */
	public function getLevel(): ?Level{
		return Server::getInstance()->getLevelByName($this->arenaWorld);
	}

	/**
	 * This function is used to handle player deaths or 'knocked outs'.
	 * It is to make sure that this player will be removed from the game correctly
	 * according to what is configured in the config file.
	 *
	 * @param Player $pl
	 */
	public function knockedOut(Player $pl){
		// Remove the player from the list.
		if(isset($this->players[strtolower($pl->getName())])) unset($this->players[strtolower($pl->getName())]);

		if($this->enableSpectator){
			$this->spectators[strtolower($pl->getName())] = $pl;
		}elseif($this->spectateWaiting > 0){
			$this->plugin->getScheduler()->scheduleDelayedTask(new PlayerDeathTask($this, $pl), 10);
		}else{
			$this->leaveArena($pl);
		}
	}

	/**
	 * Forcefully reset the arena to its original state.
	 *
	 * @param bool $resetConfig
	 */
	public function resetArena(bool $resetConfig = false){
		if($resetConfig){
			$this->data = SkyWarsPE::getInstance()->getArenaManager()->getArenaConfig($this->arenaName);

			$this->parseData();
			$this->configTeam($this->getArenaData());
		}

		$this->loadCageHandler();
		$this->saveArenaWorld();
		$this->resetPlayers();

		$this->arenaLevel = $this->getLevel();
		$this->startedTime = 0;

		if($this->gameAPI === null) $this->gameAPI = new DefaultGameAPI($this);

		// Remove the task first.
		$tasks = $this->gameAPI->getRuntimeTasks();
		if(!empty($this->taskRunning)){
			foreach($this->taskRunning as $id => $data){
				$data->cancel();

				unset($this->taskRunning[$id]);
			}
		}

		// Then commit re-run.
		foreach($tasks as $task){
			$runnable = SkyWarsPE::getInstance()->getScheduler()->scheduleDelayedRepeatingTask($task, 100, 20);
			$this->taskRunning[] = $runnable;
		}
	}

	/**
	 * Shutdown the arena forcefully.
	 */
	public function forceShutdown(){
		$this->stopGame();
		$this->gameAPI->shutdown();

		$this->unsetAllPlayers();

		if(!empty($this->taskRunning)){
			foreach($this->taskRunning as $id => $data){
				$data->cancel();

				unset($this->taskRunning[$id]);
			}
		}
	}

	/**
	 * Set the arena data. This doesn't reset the arena settings.
	 *
	 * @param Config $config
	 * @since 3.0
	 */
	public function setData(Config $config){
		$this->data = $config->getAll();
	}

	/**
	 * Return the name given for this arena.
	 *
	 * @return string
	 * @since 3.0
	 */
	public function getArenaName(): string{
		return $this->arenaName;
	}

	/**
	 * Reset the arena to its last state. In this function, the arena world will be reset and
	 * the variables will be set to its original values.
	 *
	 * @since 3.0
	 */
	public function resetArenaWorld(){
		if($this->plugin->getServer()->isLevelLoaded($this->arenaWorld)){
			$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($this->arenaWorld));
		}

		$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $this->arenaWorld;
		$toPath = $this->plugin->getServer()->getDataPath() . "/worlds/" . $this->arenaWorld;

		Utils::deleteDirectory($toPath);
		if(!Settings::$zipArchive){
			if(file_exists($toPath)){
				return;
			}

			Utils::copyResourceTo($fromPath, $toPath);
		}else{
			if(!is_file("$fromPath.zip")){
				return;
			}

			$zip = new \ZipArchive;
			if($zip->open("$fromPath.zip")){
				// Extract it to this path
				$zip->extractTo($toPath);
				$zip->close();
			}
		}
	}

	/**
	 * Returns the data of the arena.
	 *
	 * @return array
	 * @since 3.0
	 */
	public function getArenaData(){
		return $this->data;
	}

	/**
	 * Add the player to join into the arena.
	 *
	 * @param Player $pl
	 * @since 3.0
	 */
	public function joinToArena(Player $pl){
		// Maximum players reached furthermore player can't join.
		if(count($this->getPlayers()) >= $this->maximumPlayers){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-full'));

			return;
		}

		// Arena is in game.
		if($this->getStatus() >= State::STATE_ARENA_RUNNING){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-running'));

			return;
		}

		// This arena is not enabled.
		if(!$this->arenaEnable){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-disabled'));

			return;
		}

		Utils::loadFirst($this->arenaWorld); # load the level
		$this->arenaLevel = $this->plugin->getServer()->getLevelByName($this->arenaWorld); # Reset the current state of level

		// Pick one of the cages in the arena.
		/** @var Vector3 $spawnPos */
		$spawnLoc = array_rand($this->spawnPedestals);
		$spawnPos = $this->spawnPedestals[$spawnLoc];
		$this->usedPedestals[$pl->getName()] = [$spawnPos, $spawnLoc];

		// Here you can see, the code passes to the game API to check
		// If its allowed to enter the arena or not.
		if(!$this->gameAPI->joinToArena($pl)){
			unset($this->usedPedestals[$pl->getName()]);

			return;
		}

		$pl->getInventory()->setHeldItemIndex(1, true);
		$this->addPlayer($pl, $this->getRandomTeam());

		$pl->teleport(Position::fromObject($spawnPos->add(0.5, 0, 0.5), $this->getLevel()));

		unset($this->spawnPedestals[$spawnLoc]);
	}

	/**
	 * Leave a player from an arena.
	 *
	 * @param Player $pl
	 * @param bool $force
	 * @since 3.0
	 */
	public function leaveArena(Player $pl, bool $force = false){
		if(!$this->gameAPI->leaveArena($pl, $force)){
			return;
		}

		$this->removePlayer($pl);

		// Remove the spawn pedestals
		$valObj = $this->usedPedestals[$pl->getName()][0];
		$keyObj = $this->usedPedestals[$pl->getName()][1];
		$this->spawnPedestals[$keyObj] = $valObj;

		$pl->teleport(SkyWarsPE::getInstance()->getDatabase()->getLobby());

		unset($this->usedPedestals[$pl->getName()]);
	}

	/**
	 * Remove all of the players in this game.
	 */
	public function unsetAllPlayers(){
		$this->gameAPI->removeAllPlayers();

		/** @var Player $p */
		foreach(array_merge($this->players, $this->spectators) as $p){
			unset($this->players[strtolower($p->getName())]);

			$p->teleport($this->plugin->getDatabase()->getLobby());
		}
	}

	/**
	 * Get the status of the arena. Please use the constants that is
	 * set in this class to check what is the status.
	 *
	 * @return int
	 * @since 3.0
	 */
	public function getStatus(){
		return $this->arenaStatus;
	}

	/**
	 * Set the status of the arena.
	 *
	 * @param int $statusCode
	 * @since 3.0
	 */
	public function setStatus(int $statusCode){
		$this->arenaStatus = $statusCode;
	}

	private function loadCageHandler(){
		$this->freePedestals = $this->spawnPedestals; // The 'available' spawns
		$this->usedPedestals = []; // Used spawns that will be added into 'available' if the player left
	}

	private function getRandomTeam(): int{
		if(!$this->teamMode){
			return -1;
		}

		// Get the lowest members in a team
		// And use them as the player team
		asort($this->configuredTeam);

		foreach($this->configuredTeam as $colour){
			return $colour;
		}

		Utils::sendDebug("Configured team is empty?");

		return -1;
	}

	private function configTeam(array $data){
		if(!isset($data['arena-mode']) || $data['arena-mode'] == State::MODE_SOLO){
			return;
		}

		// The colours of the wool
		// [See I use color instead of colours? Blame British English]
		$colors = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
		for($color = 0; $color <= 15; $color++){
			if($color >= ($this->maximumTeams - 1)){
				break;
			}

			$randColor = array_rand($colors);
			$this->configuredTeam[$colors[$randColor]] = 0;

			unset($colors[$randColor]);
		}
	}

	private function saveArenaWorld(){
		$levelName = $this->arenaWorld;

		$fromPath = $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelName;
		$toPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName;

		// Reverted from diff 65e8fb78
		Utils::ensureDirectory($toPath);
		if(!Settings::$zipArchive){
			if(file_exists($toPath)){
				return;
			}

			// Just copy it.
			Utils::copyResourceTo($fromPath, $toPath);
		}else{
			if(is_file("$toPath.zip")){
				return;
			}

			// Get real path for our folder
			$rootPath = realpath($fromPath);

			// Initialize archive object
			$zip = new \ZipArchive();
			$zip->open("$toPath.zip", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

			// Create recursive directory iterator
			/** @var \SplFileInfo[] $files */
			$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY);

			foreach($files as $name => $file){
				// Skip directories (they would be added automatically)
				if(!$file->isDir()){
					// Get real and relative path for current file
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath, strlen($rootPath) + 1);

					// Add current file to archive
					$zip->addFile($filePath, $relativePath);
				}
			}

			// Zip archive will be created only after closing object
			$zip->close();
		}
	}

}























