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

namespace larryTheCoder\arena\api\scoreboard;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\impl\Scoreboard;
use larryTheCoder\arena\api\utils\StandardScoreboard;
use larryTheCoder\utils\Utils;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

/**
 * Internal scoreboard, this is taken from my original code from GameAPI v3.0
 * apart from that, this code performance has improved significantly.
 */
class Internal implements Scoreboard {

	private const EMPTY_CACHE = ["§0\e", "§1\e", "§2\e", "§3\e", "§4\e", "§5\e", "§6\e", "§7\e", "§8\e", "§9\e", "§a\e", "§b\e", "§c\e", "§d\e", "§e\e"];

	/** @var Player[] */
	private $scoreboards = [];
	/** @var Config */
	private $config;
	/** @var Arena */
	private $arena;
	/** @var string[][] */
	private $networkBound = [];
	/** @var string */
	private $status = TextFormat::GREEN . "Waiting...";
	/** @var int */
	private $timeLeft = 0;

	/** @var int[][] */
	private $lastState = [];

	public function __construct(Arena $arena, Config $defaultConf){
		$this->arena = $arena;

		$this->config = $defaultConf;
	}

	/**
	 * Set current event/status of an arena.
	 *
	 * @param string $status
	 */
	public function setStatus(string $status): void{
		$this->status = $status;
	}

	/**
	 * Adds a player into the scoreboard list.
	 *
	 * @param Player $player
	 */
	public function addPlayer(Player $player): void{
		$this->scoreboards[$player->getName()] = $player;

		StandardScoreboard::setScore($player, $this->config->get("display-name", "§e§lSKYWARS"), StandardScoreboard::SORT_ASCENDING);

		$this->updateScoreboard($player);
	}

	private function updateScoreboard(Player $pl): void{
		if(!isset($this->scoreboards[$pl->getName()])){
			$this->addPlayer($pl);

			return;
		}elseif(!$pl->isOnline()){
			$this->removePlayer($pl);

			return;
		}

		$pm = $this->arena->getPlayerManager();

		// Reset network cache if the state are no longer the same.
		$status = $this->arena->getStatus();
		if(($this->lastState[$pl->getName()]["arena"] ?? -1) !== $status){
			foreach(($this->networkBound[$pl->getName()] ?? []) as $line => $message){
				StandardScoreboard::setScoreLine($pl, $line, null);
			}

			$this->lastState[$pl->getName()]["arena"] = $status;
			unset($this->networkBound[$pl->getName()]);
		}elseif(($this->lastState[$pl->getName()]["status"] ?? false) !== $pm->isSpectator($pl)){
			foreach(($this->networkBound[$pl->getName()] ?? []) as $line => $message){
				StandardScoreboard::setScoreLine($pl, $line, null);
			}

			$this->lastState[$pl->getName()]["status"] = true;
			unset($this->networkBound[$pl->getName()]);
		}

		switch($status){
			case ArenaState::STATE_STARTING: // Evaluate if we do need another custom scoreboard for this
			case ArenaState::STATE_WAITING:
				$data = $this->config->get("wait-arena", [""]);
				break;
			case ArenaState::STATE_ARENA_RUNNING:
				if($this->arena->getPlayerManager()->isSpectator($pl)){
					$data = $this->config->get("spectate-scoreboard", [""]);
				}else{
					$data = $this->config->get("in-game-arena", [""]);
				}
				break;
			case ArenaState::STATE_ARENA_CELEBRATING:
				$data = $this->config->get("ending-state-arena", [""]);
				break;
			default:
				$data = null;
		}

		foreach($data as $scLine => $message){
			$line = $scLine + 1;
			$msg = $this->replaceData($pl, $line, $message);

			// Do nothing, we do not want to send the same thing all over again.
			if(($this->networkBound[$pl->getName()][$line] ?? -1) === $msg){
				continue;
			}

			StandardScoreboard::setScoreLine($pl, $line, $msg);

			$this->networkBound[$pl->getName()][$line] = $msg;
		}
	}

	private function replaceData(Player $pl, int $line, string $message): string{
		if(empty($message)) return self::EMPTY_CACHE[$line] ?? "";

		$pm = $this->arena->getPlayerManager();
		$kills = $pm->getKills($pl->getName());
		$playerPlacing = Utils::addPrefix($pm->getRanking($pl->getName()) + 1);
		$topKill = $pm->getTopKills();

		$topPlayer = $pm->getOriginName($pm->getTopPlayer());
		$search = [
			"{arena_status}",
			"{arena_mode}",
			"{arena_map}",
			"{top_player}",
			"{top_kills}",
			"{player_kills}",
			"{player_place}",
			"{players_left}",
			"{max_players}",
			"{min_players}",
			"{player_name}",
			"&",
			"{team_colour}",
			"{time_left}",
		];

		$replace = [
			$this->status,
			$this->arena->getMode() === ArenaState::MODE_SOLO ? "SOLO" : "TEAM",
			$this->arena->getMapName(),
			$topPlayer,
			$topKill,
			$kills,
			$playerPlacing,
			$this->arena->getPlayerManager()->getPlayersCount(),
			$this->arena->getMaxPlayer(),
			$this->arena->getMinPlayer(),
			$pl->getName(),
			TextFormat::ESCAPE,
			$pm->getTeamColorName($pl),
			$this->timeLeft,
		];

		foreach($pm->getWinners() as $rank => $data){
			$copy = $rank + 1;

			array_push($search, "{kills_top_$copy}");
			array_push($search, "{player_top_$copy}");
			array_push($replace, $data[1]);
			array_push($replace, $data[0]);
		}

		return str_replace($search, $replace, $message) . " ";
	}

	public function tickScoreboard(): void{
		foreach($this->arena->getPlayerManager()->getAllPlayers() as $player){
			$this->updateScoreboard($player);
		}
	}

	public function resetScoreboard(): void{
		foreach($this->scoreboards as $player){
			$this->removePlayer($player);
		}

		$this->networkBound = [];
		$this->config->reload();
	}

	public function removePlayer(Player $player): void{
		unset($this->scoreboards[$player->getName()]);
		unset($this->networkBound[$player->getName()]);

		StandardScoreboard::removeScore($player);
	}
}