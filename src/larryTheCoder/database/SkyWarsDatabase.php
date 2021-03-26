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

namespace larryTheCoder\database;

use larryTheCoder\arena\api\utils\SingletonTrait;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\PlayerData;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use RuntimeException;

class SkyWarsDatabase {
	use SingletonTrait;

	/** @var \Closure */
	private static $emptyStatement;

	/** @var DataConnector */
	private $database;

	/** @var Vector3|null */
	private $vector3 = null;
	/** @var string|null */
	private $levelName = null;

	public function __construct(){
		self::$emptyStatement = static function(): void{
		};
	}

	/**
	 * @param string[] $config
	 */
	public function createContext(array $config): void{
		$this->database = libasynql::create(SkyWarsPE::getInstance(), $config, [
			"sqlite" => "database/sqlite.sql",
			"mysql"  => "database/mysql.sql",
		]);

		$this->database->executeGeneric('table.players');
		$this->database->executeGeneric('table.lobby');
	}

	/**
	 * Creates player database entry.
	 *
	 * @param Player $player
	 */
	public static function createPlayer(Player $player): void{
		self::getInstance()->database->executeChange('data.createData', [
			"playerName" => $player->getName(),
		], self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Get player's entry from the database.
	 *
	 * @param Player|string $player Ambiguous variable, a {@link Player} class will always has its data.
	 * @param callable $onComplete The result of the search query.
	 */
	public static function getPlayerEntry($player, callable $onComplete): void{
		self::getInstance()->database->executeSelect('data.selectData', [
			"playerName" => is_string($player) ? $player : $player->getName(),
		], function(array $rows) use ($onComplete){
			if(empty($rows)){
				$onComplete(null);

				return;
			}

			$onComplete(self::parsePlayerRow($rows[0]));
		}, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Set offset data from a given player data.
	 *
	 * @param Player $player
	 * @param PlayerData $playerData
	 */
	public static function setPlayerData(Player $player, PlayerData $playerData): void{
		self::getInstance()->database->executeChange('data.changeOffset', [
			'dataOffset' => implode(" ", $playerData->permissions),
			'playerName' => $player->getName(),
		], self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Get top 5 players that won the game. These entries will be used for pedestal
	 * manager.
	 *
	 * @param callable $onComplete
	 * @param callable $onError
	 */
	public static function getEntries(callable $onComplete, callable $onError): void{
		self::getInstance()->database->executeSelect('data.selectEntries', [
		], function(array $rows) use ($onComplete){
			if(empty($rows)){
				$onComplete([]);

				return;
			}

			$data = [];
			foreach($rows as $entry){
				$data[] = (self::parsePlayerRow($entry));
			}

			$onComplete($data);
		}, static function(SqlError $result) use ($onError): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());

			$onError();
		});
	}

	/**
	 * Add players kills into the entry.
	 *
	 * @param string $playerName
	 */
	public static function addKills(string $playerName): void{
		self::getInstance()->database->executeChange('data.addKills', [
			"playerName" => $playerName,
		],  self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Add player's death entry into database, since death and lose shares the same info,
	 * we will add both of them into the entry.
	 *
	 * @param string $playerName
	 */
	public static function addDeaths(string $playerName): void{
		self::getInstance()->database->executeChange('data.addDeaths', [
			"playerName" => $playerName,
		], self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Add player's win entry into database.
	 *
	 * @param string $playerName
	 */
	public static function addWins(string $playerName): void{
		self::getInstance()->database->executeChange('data.addWins', [
			"playerName" => $playerName,
		], self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * Add played since entry into database.
	 *
	 * @param string $playerName
	 * @param int $lastPlayed
	 */
	public static function addPlayedSince(string $playerName, int $lastPlayed): void{
		self::getInstance()->database->executeChange('data.addTimer', [
			"playerName" => $playerName,
			"playerTime" => $lastPlayed,
		], self::$emptyStatement, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	public static function setLobby(Position $position): void{
		self::getInstance()->database->executeChange('data.setLobby', [
			"lobbyX"    => $position->getFloorX(),
			"lobbyY"    => $position->getFloorY(),
			"lobbyZ"    => $position->getFloorZ(),
			"worldName" => $position->getLevel()->getFolderName(),
		], function() use ($position): void{
			self::getInstance()->levelName = $position->getLevel()->getFolderName();
			self::getInstance()->vector3 = $position->asVector3();
		}, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	public static function getLobby(): Position{
		$self = self::getInstance();
		if($self->vector3 === null || $self->levelName === null){
			throw new RuntimeException("Spawn location are invalid, this shouldn't happen in the first place!");
		}
		Utils::loadFirst($self->levelName);

		return Position::fromObject($self->vector3, Server::getInstance()->getLevelByName($self->levelName));
	}

	public static function loadLobby(): void{
		self::getInstance()->database->executeSelect("data.selectLobby", [
		], function(array $rows): void{
			if(empty($rows)){
				$level = Server::getInstance()->getDefaultLevel();
				self::getInstance()->vector3 = $level->getSpawnLocation()->asVector3();
				self::getInstance()->levelName = $level->getFolderName();

				Utils::send(TextFormat::RED . "Default lobby location could not be located! Please set your lobby with /sw setlobby");
			}else{
				Utils::loadFirst($rows[0]['worldName']);

				$level = Server::getInstance()->getLevelByName($rows[0]['worldName']);

				self::getInstance()->vector3 = new Vector3(intval($rows[0]["lobbyX"]) + .5, intval($rows[0]["lobbyY"]) + .5, intval($rows[0]["lobbyZ"]) + .5);
				self::getInstance()->levelName = $level->getFolderName();
			}
		}, static function(SqlError $result): void{
			SkyWarsPE::getInstance()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
		});
	}

	/**
	 * @param mixed[] $rows
	 * @return PlayerData
	 */
	private static function parsePlayerRow(array $rows): PlayerData{
		$data = new PlayerData();
		$data->player = $rows['playerName'];
		$data->time = $rows['playerTime'];
		$data->kill = $rows['kills'];
		$data->death = $rows['deaths'];
		$data->wins = $rows['wins'];
		$data->lost = $rows['lost'];
		$data->permissions = isset($rows['data']) ? explode(" ", $rows['data']) : [];

		return $data;
	}

	public static function shutdown(): void{
		self::getInstance()->database->close();
	}
}
