<?php
/**
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

use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

/**
 * A better asynchronous database operations for SkyWars objects.
 * Uses libasyncsql in order to work properly.
 *
 * @package larryTheCoder\provider
 */
class AsyncLibDatabase {

	/** @var AsyncLibDatabase */
	public static $instance;

	const TABLE_PLAYERS = "sw.table.players";
	const TABLE_LOBBY = "sw.table.lobby";

	const TABLE_CREATE_PLAYER = "sw.create.player";
	const TABLE_CREATE_LOBBY = "sw.create.lobby";

	const TABLE_SELECT_PLAYERS = "sw.select.players";
	const TABLE_SELECT_PLAYER = "sw.select.player";
	const TABLE_SELECT_LOBBY = "sw.select.lobby";

	const TABLE_UPDATE_PLAYER = "sw.update.player";
	const TABLE_UPDATE_LOBBY = "sw.update.lobby";

	/** @var DataConnector */
	private $database;

	public function __construct(SkyWarsPE $plugin, array $config){
		$this->database = libasynql::create($plugin, $config, [
			"sqlite" => "scripts/sqlite.sql",
			"mysql"  => "scripts/mysql.sql",
		]);

		$this->database->executeGeneric(self::TABLE_PLAYERS);
		$this->database->executeGeneric(self::TABLE_LOBBY);

		self::$instance = $this;

		Utils::send(TextFormat::YELLOW . "Successfully enabled database operations.");
	}

	public function close(): void{
		$this->database->close();

		Utils::send(TextFormat::RED . "Successfully disabled database operations.");
	}

	/**
	 * Attempts to create a new player data into the table.
	 * This will only insert the player's name if it doesn't exists.
	 *
	 * @param string $playerName
	 */
	public function createNewData(string $playerName): void{
		// Just in case that the query has an unexpected results.
		$this->database->executeChange(self::TABLE_CREATE_PLAYER, ["playerName" => $playerName], function(int $affectedRows){
		});
	}

	/**
	 * Retrieves a specified player information inside the given
	 * database.
	 *
	 * @param string $playerName The player name that needs to be queried.
	 * @param callable $result The data returned with <code>function({@link PlayerData} $data) : void{}</code>
	 */
	public function getPlayerData(string $playerName, callable $result): void{
		// Gonna love PHP 7.0
		$this->database->executeSelect(self::TABLE_SELECT_PLAYER, ["playerName" => $playerName],
			function(array $rows) use ($result){
				if(empty($rows)){
					$result(null);

					return;
				}

				$result(AsyncLibDatabase::parsePlayerRow($rows[0]));
			});
	}

	/**
	 * Attempts to update the set of players inside of the database.
	 *
	 * @param string $playerName
	 * @param PlayerData $pd
	 */
	public function setPlayerData(string $playerName, PlayerData $pd): void{
		$this->database->executeChange(self::TABLE_UPDATE_PLAYER, [
			"playerName" => $playerName,
			"playerTime" => $pd->time,
			"kills"      => $pd->kill,
			"deaths"     => $pd->death,
			"wins"       => $pd->wins,
			"lost"       => $pd->lost,
			"dataOffset" => base64_encode(implode("%", $pd->cages)) . ":" . base64_encode(implode("%", $pd->kitId)),
		], function(){

		});
	}

	/**
	 * Attempts to retrieves all players registered in the database.
	 *
	 * @param callable $result a callable object with datatype: <code>function({@link PlayerData[]} $data) : void{}</code>
	 */
	public function getPlayers(callable $result): void{
		$this->database->executeSelect(self::TABLE_SELECT_PLAYERS, [],
			function(array $rows) use ($result){
				$players = [];
				foreach($rows as $id => $rowObj) $players[] = AsyncLibDatabase::parsePlayerRow($rowObj);

				$result($players);
			});
	}

	/** @var Position|null */
	private $cachedLobby = null;

	public function teleportLobby(Player $pl): void{
		if($this->cachedLobby !== null){
			$this->teleport($pl, $this->cachedLobby);

			return;
		}

		$this->database->executeSelect(self::TABLE_SELECT_LOBBY, [],
			function(array $rows) use ($pl){
				if(empty($rows)){
					$lobby = Server::getInstance()->getDefaultLevel()->getSpawnLocation();

					$this->setLobby($lobby);
					$position = $lobby;
				}else{
					Utils::loadFirst($rows[0]["worldName"], true);

					$level = Server::getInstance()->getLevelByName($rows[0]['worldName']);
					$position = new Position(intval($rows[0]["lobbyX"]) + .5, intval($rows[0]["lobbyY"]) + .5, intval($rows[0]["lobbyZ"]) + .5, $level);
				}

				$this->teleport($pl, $position);
			});
	}

	private function teleport(Player $pl, Position $position): void{
		if($pl->isConnected()){
			$pl->teleport($position);
		}else{
			$server = Server::getInstance();

			$data = $server->getOfflinePlayerData($pl->getName());
			$data->setTag(new ListTag("Pos", [
				new DoubleTag("", $position->x),
				new DoubleTag("", $position->y),
				new DoubleTag("", $position->z),
			]), true);
			$data->setTag(new StringTag("Level", $position->getLevel()->getFolderName()), true);

			$server->saveOfflinePlayerData($pl->getName(), $data);
		}
	}

	public function setLobby(Position $pos): void{
		$this->database->executeSelect(self::TABLE_SELECT_LOBBY, [],
			function(array $rows) use ($pos){
				if(empty($rows)){
					$this->database->executeInsert(self::TABLE_CREATE_LOBBY, [
						"lobbyX"    => $pos->getFloorX(),
						"lobbyY"    => $pos->getFloorY(),
						"lobbyZ"    => $pos->getFloorZ(),
						"worldName" => $pos->getLevel()->getName(),
					]);
				}else{
					$lastLevel = $rows[0]["worldName"];
					$this->database->executeInsert(self::TABLE_UPDATE_LOBBY, [
						"lobbyX"        => $pos->getFloorX(),
						"lobbyY"        => $pos->getFloorY(),
						"lobbyZ"        => $pos->getFloorZ(),
						"worldName"     => $pos->getLevel()->getName(),
						"worldNameData" => $lastLevel,
					]);
				}

				$this->cachedLobby = $pos;
			});
	}

	private static function parsePlayerRow(array $rows): PlayerData{
		$data = new PlayerData();
		$data->player = $rows['playerName'];
		$data->time = $rows['playerTime'];
		$data->kill = $rows['kills'];
		$data->death = $rows['deaths'];
		$data->wins = $rows['wins'];
		$data->lost = $rows['lost'];

		if(isset($rows["data"])){
			$erData = explode(":", $rows["data"]);
			$cages = explode("%", base64_decode($erData[0]));
			$kits = explode("%", base64_decode($erData[1]));

			$data->cages = $cages;
			$data->kitId = $kits;
		}

		return $data;
	}
}