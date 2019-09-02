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
use larryTheCoder\events\PlayerJoinArenaEvent;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
use pocketmine\Player;

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
	/** @var array */
	private $data;
	/** @var int */
	private $arenaStatus = self::STATE_WAITING;

	/** @var DefaultGameAPI|GameAPI */
	public $gameAPI;

	/** @var Position[] */
	private $freePedestals;
	/** @var Position[] */
	private $usedPedestals;
	/** @var \pocketmine\level\Level|null */
	private $arenaLevel;

	public function __construct(string $arenaName, SkyWarsPE $plugin){
		$this->arenaName = $arenaName;
		$this->plugin = $plugin;
		$this->data = $plugin->getArenaManager()->getArenaConfig($arenaName);

		$this->parseData();
		$this->loadCageHandler();
		$this->gameAPI = new DefaultGameAPI($this);
	}

	private function loadCageHandler(){
		$this->freePedestals = $this->spawnPedestals; // The 'available' spawns
		$this->usedPedestals = []; // Used spawns that will be added into 'available' if the player left
	}

	/**
	 * Returns the data of the arena.
	 *
	 * @return array
	 */
	public function getArenaData(){
		return $this->data;
	}

	/**
	 * Add the player to join into the arena.
	 *
	 * @param Player $pl
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

		// As usual, use events as in the plugin to avoid API break?
		$e = new PlayerJoinArenaEvent($this->plugin, $pl, $this);
		try{
			$e->call();
		}catch(\ReflectionException $e){
		}

		if($e->isCancelled()){
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
		$this->messageArenaPlayers('player-join-2', true, ['{PLAYER}'], [$pl->getName()]);
		$this->addPlayer($pl);

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
	 */
	public function getStatus(){
		return $this->arenaStatus;
	}

	/**
	 * Set the status of the arena.
	 *
	 * @param int $statusCode
	 */
	public function setStatus(int $statusCode){
		$this->arenaStatus = $statusCode;
	}
}























