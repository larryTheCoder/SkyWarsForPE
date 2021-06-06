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
use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\utils\Utils;
use pocketmine\block\utils\ColorBlockMetaHelper;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use RuntimeException;

class PlayerManager {

	public const BLOCK_META_TO_TF = [
		0  => TextFormat::WHITE,
		1  => TextFormat::GOLD,
		2  => TextFormat::LIGHT_PURPLE,
		3  => TextFormat::BLUE,
		4  => TextFormat::YELLOW,
		5  => TextFormat::GREEN,
		7  => TextFormat::DARK_GRAY,
		8  => TextFormat::GRAY,
		9  => TextFormat::AQUA,
		10 => TextFormat::DARK_PURPLE,
		11 => TextFormat::DARK_BLUE,
		13 => TextFormat::DARK_GREEN,
		14 => TextFormat::RED,
		15 => TextFormat::BLACK,
	];

	//
	// Take note that these arrays (Player names) are stored in
	// lowercase letters. Use the function given to access the right player name
	//

	/** @var bool */
	public $teamMode = false;
	/** @var int */
	public $maximumMembers = 0;
	/** @var int */
	public $maximumTeams = 0;
	/** @var int[] */
	public $allowedTeams = [];

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

	/** @var string[] */
	private $ranking = [];
	/** @var int[] */
	public $kills = [];

	public function __construct(Arena $arena){
		$this->arena = $arena;
	}

	public function addPlayer(Player $pl, int $team = -1): void{
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

		// Check if the arena is in team mode.
		if($this->teamMode){
			$this->teams[strtolower($pl->getName())] = $team === -1 ? $this->getRandomTeam() : $team;
		}
	}

	/**
	 * Returns the string/player object of a player themselves. This will return the players that is in
	 * the same team as the given player variable.
	 *
	 * @param Player $player The player variable that will be used to check.
	 * @param bool $asString Return the value as a string, it will return {@link Player} if otherwise.
	 * @return string[]|Player[]
	 */
	public function getTeammates(Player $player, bool $asString = true): array{
		$rawTeam = $this->getTeamColorRaw($player);

		$assocDiff = array_keys(array_filter($this->teams, function($value) use ($rawTeam): bool{
			return $value === $rawTeam;
		}));

		if($asString){
			return $assocDiff;
		}else{
			$players = [];
			foreach($assocDiff as $player){
				$pl = $this->getOriginPlayer($player);
				if($pl !== null){
					$players[] = $pl;
				}
			}

			return $players;
		}
	}

	/**
	 * Check if both player is the same teammates.
	 *
	 * @param Player $damager
	 * @param Player $player
	 * @return bool
	 */
	public function isTeammates(Player $damager, Player $player): bool{
		if($this->teamMode){
			return $this->getTeamColorRaw($damager) === $this->getTeamColorRaw($player);
		}else{
			return false;
		}
	}

	/**
	 * Fetch random team in an associative array within available team and picked teams.
	 * This team differentiates in which one will be chosen, where available team will always be chosen first.
	 *
	 * @return int The team that is available,
	 */
	public function getRandomTeam(): int{
		$teamData = array_count_values($this->teams);

		// Test for potential teams indexed by first to end.
		$potentialTeam = -1;
		foreach($teamData as $team => $count){
			if($count < $this->maximumMembers){
				$potentialTeam = $team;
				break;
			}
		}

		// Only index more teams if the amount of teams available are not reaching maximum teams
		if($potentialTeam === -1 && count($teamData) < $this->maximumTeams){
			$team = array_diff_assoc($this->allowedTeams, array_keys($teamData));
			if(empty($team)) throw new RuntimeException("Configuration error for SW: Missing more teams (Configure this again correctly | Arena {$this->arena->getMapName()})");

			foreach($team as $pt){
				$potentialTeam = $pt;
				break;
			}
		}

		return $potentialTeam;
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
	public function setPlayerTeam(Player $pl, int $team): void{
		if(!$this->teamMode){
			return;
		}

		$this->teams[strtolower($pl->getName())] = $team;
	}

	/**
	 * @param string $key
	 * @param bool $popup
	 * @param mixed[] $replacements
	 */
	public function broadcastToPlayers(string $key, bool $popup = false, array $replacements = []): void{
		$inGame = array_merge($this->getAlivePlayers(), $this->getSpectators());
		/** @var Player $p */
		foreach($inGame as $p){
			if($popup){
				$p->sendPopup(TranslationContainer::getTranslation($p, $key, $replacements));
			}else{
				$p->sendMessage(TranslationContainer::getTranslation($p, $key, $replacements));

				Utils::addSound([$p], "random.pop");
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
	 * @param string|null $return
	 * @return string The original name of this player.
	 */
	public function getOriginName(string $name, ?string $return = null): ?string{
		if(!$this->isInArena($name)){
			return $return;
		}

		$pl = $this->getOriginPlayer($name);

		if($this->teamMode){
			return $this->getColorByMeta($this->getTeamColorRaw($pl)) . $pl->getName();
		}else{
			return $pl->getName();
		}
	}

	/**
	 * Checks either the player is inside the arena or not.
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

	public function getPlayerState(Player $sender): int{
		if(isset($this->players[strtolower($sender->getName())])){
			return ArenaState::PLAYER_ALIVE;
		}elseif(isset($this->spectators[strtolower($sender->getName())])){
			return ArenaState::PLAYER_SPECTATE;
		}elseif($this->arenaLevel !== null && strtolower($sender->getLevel()->getName()) === strtolower($this->arenaLevel->getName())){
			return ArenaState::PLAYER_SPECIAL;
		}else{
			return ArenaState::PLAYER_UNSET;
		}
	}

	/**
	 * Reset players data and return the players object.
	 *
	 * @return Player[][]
	 */
	public function resetPlayers(): array{
		$data = [];
		$data["player"] = $this->players;
		$data["spectator"] = $this->spectators;

		$this->spectators = [];
		$this->players = [];
		$this->teams = [];
		$this->kills = [];

		return $data;
	}

	public function updateWinners(): void{
		$this->ranking = [];

		$sort = $this->kills;
		arsort($sort);

		$i = 0;
		foreach($sort as $player => $kills){
			if(!is_string($player)) continue;

			$this->ranking[$i++] = $player;
		}
	}

	public function addKills(string $target): void{
		$this->kills[strtolower($target)] = $this->getKills($target) + 1;

		$player = $this->getOriginPlayer($target);
		if($player !== null && $player->isOnline()){
			Utils::addSound([$player], "note.hat");
		}
	}

	public function getKills(string $target): int{
		return $this->kills[strtolower($target)] ?? 0;
	}

	public function isSolo(): bool{
		return !$this->teamMode;
	}

	public function isSpectator(Player $player): bool{
		return in_array($player, $this->spectators, true);
	}

	public function broadcastTitle(string $title, string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1): void{
		foreach($this->getAllPlayers() as $player){
			$title = TranslationContainer::getTranslation($player, $title);
			$subtitle = TranslationContainer::getTranslation($player, $subtitle);

			$player->sendTitle($title, $subtitle, $fadeIn, $stay, $fadeOut);
		}
	}

	public function getRanking(string $playerName): int{
		return array_keys($this->ranking, strtolower($playerName), true)[0] ?? -1;
	}

	public function getTopPlayer(): string{
		return (string)array_keys($this->kills, max($this->kills))[0] ?? "N/A";
	}

	public function getTopKills(): int{
		return max($this->kills);
	}

	/**
	 * @return array<int, array<int, string|int>>
	 */
	public function getWinners(): array{
		$lastRank = 0;

		$winners = [];
		foreach($this->ranking as $rank => $playerName){
			$winners[$rank] = [$this->getOriginName($playerName, "N/A"), $this->kills[$playerName] ?? 0];

			$lastRank = $rank;
		}

		while($lastRank < $this->arena->getMaxPlayer()){
			$winners[++$lastRank] = ["N/A", 0];
		}

		return $winners;
	}

	public function getTeamColorRaw(Player $player): int{
		$color = $this->teams[strtolower($player->getName())] ?? null;
		if($color === null) return -1;

		return $color;
	}

	public function getTeamColorName(Player $pl): ?string{
		if(($color = $this->getTeamColorRaw($pl)) === -1) return null;

		return ColorBlockMetaHelper::getColorFromMeta($color);
	}

	/**
	 * Returns a matching meta value for a TextFormat color constant.
	 *
	 * @param string $color a TextFormat constant
	 * @return int Meta value, returns -1 if failed
	 */
	public static function getMetaByColor(string $color): int{
		$search = array_search($color, self::BLOCK_META_TO_TF, true);

		return $search !== false ? (int)$search : -1;
	}

	/**
	 * Returns a matching TextFormat color constant from meta values.
	 *
	 * @param int $meta
	 * @return string $color a TextFormat constant
	 */
	public static function getColorByMeta(int $meta = -1): string{
		return self::BLOCK_META_TO_TF[$meta] ?? "";
	}
}