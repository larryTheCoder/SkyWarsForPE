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

namespace larryTheCoder\arenaRewrite;

use larryTheCoder\arenaRewrite\api\DefaultGameAPI;
use larryTheCoder\arenaRewrite\api\GameAPI;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\TaskHandler;
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

	const MODE_SOLO = 0;
	const MODE_TEAM = 1;

	const STATE_WAITING = 0;
	const STATE_SLOPE_WAITING = 1;
	const STATE_ARENA_RUNNING = 2;
	const STATE_ARENA_CELEBRATING = 3;

	/*** @var SkyWarsPE */
	private $plugin;
	/** @var int */
	private $arenaStatus = self::STATE_WAITING;

	/** @var GameAPI */
	public $gameAPI;
	/** @var array */
	public $data;

	/** @var Position[] */
	private $freePedestals;
	/** @var Position[] */
	private $usedPedestals;
	/** @var \pocketmine\level\Level|null */
	private $arenaLevel;

	/** @var TaskHandler[] */
	private $taskRunning = [];

	public function __construct(string $arenaName, SkyWarsPE $plugin){
		$this->arenaName = $arenaName;
		$this->plugin = $plugin;
		$this->data = $plugin->getArenaManager()->getArenaConfig($arenaName);
		$this->gameAPI = new DefaultGameAPI($this);

		$this->resetArena();
	}

	/**
	 * Forcefully reset the arena to its original state.
	 */
	public function resetArena(){
		$this->parseData();
		$this->loadCageHandler();
		$this->saveArenaWorld();
		$this->configTeam($this->getArenaData());

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
		$this->gameAPI->shutdown();

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
		$levelName = $this->data['arena']['arena_world'];
		if($this->plugin->getServer()->isLevelLoaded($levelName)){
			$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($levelName));
		}

		$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName;
		$toPath = $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelName;

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
		if($this->getPlayers() >= $this->maximumPlayers){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-full'));

			return;
		}

		// Arena is in game.
		if($this->getStatus() >= self::STATE_ARENA_RUNNING){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-running'));

			return;
		}

		// This arena is not enabled.
		if(!$this->arenaEnable){
			$pl->sendMessage($this->plugin->getMsg($pl, 'arena-disabled'));

			return;
		}

		Utils::loadFirst($this->data['arena']['arena_world']); # load the level
		$this->arenaLevel = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); # Reset the current state of level

		// Here you can see, the code passes to the game API to check
		// If its allowed to enter the arena or not.
		if(!$this->gameAPI->joinToArena($pl)){
			return;
		}

		$pl->getInventory()->setHeldItemIndex(1, true);
		$this->addPlayer($pl, $this->getRandomTeam());

		// Pick one of the cages in the arena.
		$spawnLoc = array_rand($this->spawnPedestals);
		$spawnPos = $this->spawnPedestals[$spawnLoc];
		$this->usedPedestals[$pl->getName()] = [$spawnPos, $spawnPos];

		$pl->teleport($spawnPos);

		unset($this->spawnPedestals[$spawnLoc]);
	}

	/**
	 * Leave a player from an arena.
	 *
	 * @param Player $pl
	 * @since 3.0
	 */
	public function leaveArena(Player $pl){
		if(!$this->gameAPI->leaveArena($pl)){
			return;
		}

		$this->removePlayer($pl);
	}

	/**
	 * Get the status of the arena.
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
		if($data['arena-mode'] == Arena::MODE_SOLO){
			return;
		}

		Utils::sendDebug("Overriding {$this->arenaName} default players settings");

		$this->maximumTeams = $data['team-settings']['world-teams-avail'];     // Maximum teams   in arena
		$this->maximumMembers = $data['team-settings']['players-per-team'];    // Maximum members in team
		$this->maximumPlayers = $this->maximumMembers * $this->maximumTeams;   // Maximum players in arena
		$this->minimumPlayers = $this->minimumMembers * $this->maximumTeams;   // Minimum players in arena

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























