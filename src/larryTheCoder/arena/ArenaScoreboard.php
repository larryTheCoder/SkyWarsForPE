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

use larryTheCoder\utils\scoreboard\StandardScoreboard;
use larryTheCoder\utils\Utils;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

/**
 * A Scoreboard interface class
 * This class handles everything regarding to
 * scoreboards.
 *
 * @package larryTheCoder\arena
 */
class ArenaScoreboard extends Task {

	/** @var Player[] */
	private $scoreboards = [];
	/** @var Config */
	private $config;
	/** @var Arena */
	private $arena;

	public function __construct(Arena $arena){
		$this->arena = $arena;
		$this->config = Utils::loadDefaultConfig();
	}

	/**
	 * Adds a player into the scoreboard list.
	 *
	 * @param Player $pl
	 */
	public function addPlayer(Player $pl){
		$this->scoreboards[$pl->getName()] = $pl;
		StandardScoreboard::setScore($pl, "§e§lSKYWARS", 1);
	}

	// PLAYER WAITING/STARTING

	//      SKYWARS
	// Status:
	// Waiting... / Starting in...
	//
	// Map: {arena_map}
	// Mode: {arena_mode}
	//
	// www.hyrulePE.xyz


	// PLAYER IN GAME/SPECTATOR

	//      SKYWARS
	// You're in 3rd place
	//
	// Events:
	// {arena_status}
	//
	// Players left: {players_left}
	//
	// Kills: {player_kills}
	//
	// Map: {arena_map}
	// Mode: {arena_mode}
	//
	// www.hyrulePE.xyz


	// PLAYER GAME ENDED

	//         SKYWARS
	// Top winners
	// 1. {player_top_1} {kills}
	// 2. {player_top_2) {kills}
	// 3. {player_top_3} {kills}
	//
	// Map: {arena_map}
	// Mode: {arena_mode}
	//
	// www.hyrulePE.xyz

	/** @var array[] */
	private $tempEmptyCache = [];

	public function passData(Player $pl, string &$line, bool $isSpectator){
		if(!isset($this->tempEmptyCache[$pl->getName()])){
			// Temporary spaces.
			$this->tempEmptyCache[$pl->getName()] = ["§0\e", "§1\e", "§2\e", "§3\e", "§4\e", "§5\e", "§6\e", "§7\e", "§8\e", "§9\e", "§a\e", "§b\e", "§c\e", "§d\e", "§e\e"];
		}

		if(empty($line)){
			foreach($this->tempEmptyCache[$pl->getName()] as $obj => $image){
				$line = $image;
				unset($this->tempEmptyCache[$pl->getName()][$obj]);

				return;
			}
		}


	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		switch($this->arena->getMode()){
			case Arena::ARENA_WAITING_PLAYERS:
				$data = $this->config->get("wait-arena", [""]);
				break;
			case Arena::ARENA_RUNNING:
				$data = $this->config->get("in-game-arena", [""]);
				break;
			case Arena::ARENA_CELEBRATING:
				$data = $this->config->get("ending-state-arena", [""]);
				break;
			default:
				$data = null;
		}

		// Feed the scoreboard to the players
		// That is alive.
		foreach($this->arena->players as $pl){
			foreach($data as $line => $message){
				$pLine = $this->passData($pl, $message, false);
				StandardScoreboard::setScoreLine($pl, $line + 1, $pLine);
			}
		}

		// Do the same thing to the spectators.
		foreach($this->arena->spec as $pl){
			foreach($data as $line => $message){
				$pLine = $this->passData($pl, $message, true);
				StandardScoreboard::setScoreLine($pl, $line + 1, $pLine);
			}
		}
	}
}