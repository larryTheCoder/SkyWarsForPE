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

namespace larryTheCoder\arena\api;

use larryTheCoder\arena\api\impl\ArenaState;
use pocketmine\block\utils\ColorBlockMetaHelper;
use pocketmine\level\Level;
use pocketmine\level\sound\ClickSound;
use pocketmine\Player;

class PlayerManager {

	//
	// Take note that these arrays (Player names) are stored in
	// lowercase letters. Use the function given to access the right player name
	//

	/** @var int[] */
	public $kills = [];
	/** @var bool */
	public $teamMode = false;
	/** @var int */
	public $maximumMembers = 0;
	/** @var int */
	public $maximumTeams = 0;
	/** @var int */
	public $minimumMembers = 0;
	/** @var Player[] */
	private $players = [];
	/** @var Player[] */
	private $spectators = [];
	/** @var Level|null */
	private $arenaLevel;
	/** @var int[] */
	private $teams = []; // "Player" => "Team color"
	/** @var Arena */
	private $arena;
	/** @var int[] */
	private $ranking = [];

	/** @var Player[] */
	private $playerQueue = [];

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	/**
	 * Attempt to add player into the arena queue. This holds the player queue until the next tick.
	 * This queue will be processed in ArenaTickTask.
	 *
	 * @param Player $player
	 */
	public function addQueue(Player $player): void{
		$this->playerQueue[$player->getName()] = $player;
	}

	public function inQueue(Player $player): bool{
		return isset($this->playerQueue[$player->getName()]);
	}

	public function hasQueue(): bool{
		return !empty($this->playerQueue);
	}

	/**
	 * @return Player[]
	 * @internal
	 */
	public function getQueue(): array{
		if(!empty($queue = $this->playerQueue)){
			$this->playerQueue = [];
		}

		return $queue;
	}

	public function addPlayer(Player $pl, int $team = -1){
		# Set the player gamemode first
		$pl->setGamemode(0);
		$pl->getInventory()->clearAll();
		$pl->getArmorInventory()->clearAll();

		# Set the player health and food
		$pl->setMaxHealth(20);
		$pl->setMaxHealth($pl->getMaxHealth());
		# just to be really sure
		if($pl->getAttributeMap() != null){
			$pl->setHealth(20);
			$pl->setFood(20);
		}

		$this->players[strtolower($pl->getName())] = $pl;
		$this->kills[strtolower($pl->getName())] = 0;
		$this->ranking[] = strtolower($pl->getName());

		// Check if the arena is in team mode.
		if($this->teamMode){
			$this->teams[strtolower($pl->getName())] = $team;
		}
	}

	public function setSpectator(Player $pl): void{
		$this->removePlayer($pl);
		$this->spectators[strtolower($pl->getName())] = $pl;
	}

	public function removePlayer(Player $pl): void{
		unset($this->players[strtolower($pl->getName())]);
		unset($this->teams[strtolower($pl->getName())]);
		unset($this->spectators[strtolower($pl->getName())]);
	}

	/**
	 * Set the player team for the user.
	 *
	 * @param Player $pl
	 * @param int $team
	 */
	public function setPlayerTeam(Player $pl, int $team){
		if(!$this->teamMode){
			return;
		}

		$this->teams[strtolower($pl->getName())] = $team;
	}

	public function broadcastToPlayers(string $msg, $popup = true, $toReplace = [], $replacement = []): void{
		$inGame = array_merge($this->getAlivePlayers(), $this->getSpectators());
		/** @var Player $p */
		foreach($inGame as $p){
			if($popup){
				$p->sendPopup(str_replace($toReplace, $replacement, $msg));
			}else{
				$p->sendMessage(str_replace($toReplace, $replacement, $msg));

				$p->getLevel()->addSound(new ClickSound($p));
			}
		}
	}

	/**
	 * @return Player[]
	 */
	public function getAlivePlayers(): array{
		return $this->players;
	}

	/**
	 * @return Player[]
	 */
	public function getSpectators(): array{
		return $this->spectators;
	}

	/**
	 * Return the default player name for this string. This function is perhaps
	 * to get the player information within this class.
	 *
	 * @param string $name
	 *
	 * @return string The original name of this player.
	 */
	public function getOriginName(string $name): ?string{
		if(!$this->isInArena($name)){
			return null;
		}

		return $this->getOriginPlayer($name)->getName();
	}

	/**
	 * Checks either the player is inside
	 * the arena or not.
	 *
	 * @param mixed $pl
	 *
	 * @return bool
	 */
	public function isInArena($pl): bool{
		if($pl instanceof Player){
			return isset($this->players[strtolower($pl->getName())]) || isset($this->spectators[strtolower($pl->getName())]);
		}elseif(is_string($pl)){
			return isset($this->players[strtolower($pl)]) || isset($this->spectators[strtolower($pl)]);
		}else{
			return false;
		}
	}

	/**
	 * Return the player class code for this string name. It checks if this handler
	 * stores its data inside the arrays.
	 *
	 * @param string $name
	 *
	 * @return Player The player itself.
	 */
	public function getOriginPlayer(string $name): ?Player{
		if(isset($this->players[strtolower($name)])){
			return $this->players[strtolower($name)];
		}elseif(isset($this->spectators[strtolower($name)])){
			return $this->spectators[strtolower($name)];
		}else{
			return null;
		}
	}

	/**
	 * Get the number of players in the arena.
	 * This returns the number of player that is alive
	 * inside the arena.
	 *
	 * @return int
	 */
	public function getPlayersCount(): int{
		return count($this->players);
	}

	/**
	 * @return Player[]
	 */
	public function getAllPlayers(): array{
		return array_merge($this->players, $this->spectators);
	}

	/**
	 * This will return the number of spectators
	 * that is inside the arena.
	 *
	 * @return int
	 */
	public function getSpectatorsCount(): int{
		return count($this->spectators);
	}

	/**
	 * Get the teams registered in this arena.
	 *
	 * @return int[]
	 */
	public function getAliveTeam(): array{
		return $this->teams;
	}

	public function getPlayerState($sender): int{
		if($sender instanceof Player){
			if(isset($this->players[strtolower($sender->getName())])){
				return ArenaState::PLAYER_ALIVE;
			}elseif(isset($this->spectators[strtolower($sender->getName())])){
				return ArenaState::PLAYER_SPECTATE;
			}elseif($this->arenaLevel !== null && strtolower($sender->getLevel()->getName()) === strtolower($this->arenaLevel->getName())){
				return ArenaState::PLAYER_SPECIAL;
			}
		}

		return ArenaState::PLAYER_UNSET;
	}

	/**
	 * Reset players data and return the players object.
	 *
	 * @return Player[]
	 */
	public function resetPlayers(): array{
		$data = [];
		$data["player"] = $this->players;
		$data["spectator"] = $this->spectators;

		$this->spectators = [];
		$this->players = [];
		$this->teams = [];
		$this->kills = [];
		$this->winners = [];

		$i = $this->arena->getMaxPlayer() - 1;
		while($i >= 0){
			if(!isset($this->winners[$i])) $this->winners[$i] = 0;

			$i--;
		}

		return $data;
	}

	public function updateWinners(){
		$sort = $this->kills;
		arsort($sort);

		$i = 0;
		foreach($sort as $player => $kills){
			if(($oldPlayer = $this->ranking[$i]) !== $player){
				$rank = $this->getRanking($player);

				$this->ranking[$i] = $player;
				$this->ranking[$rank] = $oldPlayer;
			}
			$i++;
		}
	}

	public function addKills(string $target): void{
		$this->kills[$target] = $this->getKills($target) + 1;
	}

	public function getKills(string $target): int{
		return $this->kills[$target] ?? 0;
	}

	public function isSolo(): bool{
		return !$this->teamMode;
	}

	public function isSpectator(string $player){
		return in_array($player, $this->spectators, true);
	}

	public function broadcastTitle(string $title, string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1){
		foreach($this->getAllPlayers() as $player){
			$player->sendTitle($title, $subtitle, $fadeIn, $stay, $fadeOut);
		}
	}

	public function getRanking(string $playerName){
		return array_keys($this->ranking, $playerName, true)[0] ?? -1;
	}

	public function getTopPlayer(){
		return array_keys($this->kills, max($this->kills))[0] ?? "N/A";
	}

	public function getTopKills(){
		return max($this->kills);
	}

	public function getWinners(): array{
		$winners = [];
		foreach($this->ranking as $rank => $playerName){
			$winners[$rank] = [$playerName, $this->kills[$playerName] ?? 0];
		}

		return $winners;
	}

	public function getTeamColor(Player $pl): ?string{
		$color = $this->teams[strtolower($pl->getName())] ?? null;
		if($color === null) return null;

		return ColorBlockMetaHelper::getColorFromMeta($color);
	}
}