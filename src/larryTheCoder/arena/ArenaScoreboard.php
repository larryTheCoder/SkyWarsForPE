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
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

/**
 * A Scoreboard interface class
 * This class handles everything regarding to
 * scoreboards.
 *
 * @package larryTheCoder\arena
 */
class ArenaScoreboard {

	/** @var Player[] */
	private $scoreboards = [];
	/** @var Config */
	private $config;
	/** @var Arena */
	private $arena;

	/** @var string */
	private $events;

	public function __construct(Arena $arena){
		$this->arena = $arena;
		$this->config = Utils::loadDefaultConfig();

		$this->events = "Nothing";
	}

	/**
	 * Sets the current event of the arena.
	 * This is to show the current status or
	 * events that is happening inside the arena.
	 *
	 * @param string $event
	 */
	public function setCurrentEvent(string $event){
		if(empty($event)){
			$event = "Nothing";
		}
		$this->events = $event;
	}

	/**
	 * Adds a player into the scoreboard list.
	 *
	 * @param Player $pl
	 */
	public function addPlayer(Player $pl){
		$this->scoreboards[$pl->getName()] = $pl;
		$this->updateScoreboard($pl);
	}

	/**
	 * Removes the player from the list.
	 *
	 * @param Player $pl
	 */
	public function removePlayer(Player $pl){
		if(isset($this->scoreboards[$pl->getName()])){
			unset($this->scoreboards[$pl->getName()]);
		}

		StandardScoreboard::removeScore($pl);
	}

	/** @var array[] */
	private $tempEmptyCache = [];

	public function passData(Player $pl, string $line): string{
		if(!isset($this->tempEmptyCache[$pl->getName()])){
			// Temporary spaces. Ah thanks mojang, wait no.
			$this->tempEmptyCache[$pl->getName()] = ["§0\e", "§1\e", "§2\e", "§3\e", "§4\e", "§5\e", "§6\e", "§7\e", "§8\e", "§9\e", "§a\e", "§b\e", "§c\e", "§d\e", "§e\e"];
		}

		if(empty($line)){
			foreach($this->tempEmptyCache[$pl->getName()] as $obj => $image){
				unset($this->tempEmptyCache[$pl->getName()][$obj]);

				return $image;
			}
		}

		// Arrays really likes to complains if there is the object
		// doesn't exists
		$kills = isset($this->arena->kills[strtolower($pl->getName())])
			? $this->arena->kills[strtolower($pl->getName())] : 0;
		$playerPlacing = isset($this->arena->winnersFixed[strtolower($pl->getName())])
			? Utils::addPrefix($this->arena->winnersFixed[strtolower($pl->getName())]) : "Not ranked";
		$topPlayer = (isset($this->arena->winners[0]) && isset($this->arena->winners[0][0]))
			? $this->arena->winners[0][0] : "No data";
		$topKill = (isset($this->arena->winners[0]) && isset($this->arena->winners[0][1]))
			? $this->arena->winners[0][1] : "No data";
		$topPlayer = isset($this->arena->playerNameFixed[$topPlayer])
			? $this->arena->playerNameFixed[$topPlayer] : $topPlayer;

		// Tags information..?
		$search = [
			"{arena_mode}",
			"{arena_map}",
			"{arena_status}",
			"{top_player}",
			"{top_kills}",
			"{player_kills}",
			"{player_place}",
			"{players_left}",
			"{max_players}",
			"{min_players}",
			"{player_name}",
			"&",
		];
		$replace = [
			$this->arena->getArenaMode(),
			$this->arena->getArenaName(),
			$this->events,
			$topPlayer,
			$topKill,
			$kills,
			$playerPlacing,
			$this->arena->getPlayers(),
			$this->arena->getMaxPlayers(),
			$this->arena->getMinPlayers(),
			$pl->getName(),
			TextFormat::ESCAPE,
		];

		foreach($this->arena->winners as $i => $data){
			/** @var string $plName */
			$plName = $data[0];
			$plName = isset($this->arena->playerNameFixed[$plName])
				? $this->arena->playerNameFixed[$plName] : $plName;

			array_push($search, "{kills_top_" . ($i + 1) . "}");
			array_push($search, "{player_top_" . ($i + 1) . "}");
			array_push($replace, $data[1]);
			array_push($replace, $plName);
		}

		return " " . str_replace($search, $replace, $line);
	}

	public function updateScoreboard(Player $pl){
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

		if(!$pl->isOnline()){
			unset($this->scoreboards[$pl->getName()]);

			return;
		}

		StandardScoreboard::setScore($pl, $this->config->get("display-name", "§e§lSKYWARS"), 1);
		foreach($data as $line => $message){
			$pLine = $this->passData($pl, $message);
			StandardScoreboard::setScoreLine($pl, $line + 1, $pLine);
		}

		$this->tempEmptyCache = []; // Reset the cache.
	}
}