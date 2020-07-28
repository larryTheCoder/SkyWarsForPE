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

namespace larryTheCoder\provider;

use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
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

	public function close(){
		$this->database->close();

		Utils::send(TextFormat::RED . "Successfully disabled database operations.");
	}

	/**
	 * Attempts to create a new player data into the table.
	 * This will only insert the player's name if it doesn't exists.
	 *
	 * @param string $playerName
	 */
	public function createNewData(string $playerName){
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
	public function getPlayerData(string $playerName, callable $result){
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
	public function setPlayerData(string $playerName, PlayerData $pd){
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
	public function getPlayers(callable $result){
		$this->database->executeSelect(self::TABLE_SELECT_PLAYERS, [],
			function(array $rows) use ($result){
				$players = [];
				foreach($rows as $id => $rowObj) $players[] = AsyncLibDatabase::parsePlayerRow($rowObj);

				$result($players);
			});
	}

	public function teleportLobby(callable $position){
		$this->database->executeSelect(self::TABLE_SELECT_LOBBY, [],
			function(array $rows) use ($position){
				if(empty($rows)){
					$position(Server::getInstance()->getDefaultLevel()->getSpawnLocation());
				}else{
					$exec = $rows[0];

					Utils::loadFirst($exec["worldName"]);
					$level = Server::getInstance()->getLevelByName($exec['worldName']);
					$position(new Position($exec["lobbyX"], $exec["lobbyY"], $exec["lobbyZ"], $level));
				}
			});
	}

	public function setLobby(Position $pos){
		$this->database->executeSelect(self::TABLE_SELECT_LOBBY, [],
			function(array $rows) use ($pos){
				$db = AsyncLibDatabase::$instance->database;
				if(empty($rows)){
					$db->executeInsert(AsyncLibDatabase::TABLE_CREATE_LOBBY, [
						"lobbyX"    => $pos->getFloorX(),
						"lobbyY"    => $pos->getFloorY(),
						"lobbyZ"    => $pos->getFloorZ(),
						"worldName" => $pos->getLevel()->getName(),
					], function(int $changedRow){
						var_dump($changedRow);
					});
				}else{
					$lastLevel = $rows[0]["worldName"];
					$db->executeInsert(AsyncLibDatabase::TABLE_UPDATE_LOBBY, [
						"lobbyX"        => $pos->getFloorX(),
						"lobbyY"        => $pos->getFloorY(),
						"lobbyZ"        => $pos->getFloorZ(),
						"worldName"     => $pos->getLevel()->getName(),
						"worldNameData" => $lastLevel,
					], function(int $changedRow){
						var_dump($changedRow);
					});
				}
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