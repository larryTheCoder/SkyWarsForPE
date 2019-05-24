<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
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
use larryTheCoder\utils\{Settings, Utils};
use pocketmine\level\Position;
use pocketmine\Server;

class SQLite3Database extends SkyWarsDatabase {

	/** @var \Sqlite3 */
	private $db;
	/** @var \SQLite3Stmt */
	private $sqlCreateNewData, $sqlGetPlayerData, $sqlUpdateNewData, $sqlGetLobbyPos, $sqlGetLobbyInsert, $sqlGetLobbyUpdate;
	/** @var Position */
	private $positionCache;

	public function __construct(SkyWarsPE $plugin){
		parent::__construct($plugin);
		$this->init();
		$this->logger->info($plugin->getPrefix() . "§bSuccessfully loaded the sqlite driver");
	}

	private function init(){
		$this->db = new \SQLite3(Settings::$sqlitePath);
		$this->db->exec("CREATE TABLE IF NOT EXISTS players(playerName VARCHAR(32) NOT NULL, playerTime INTEGER DEFAULT 0, kills INTEGER DEFAULT 0, deaths INTEGER DEFAULT 0, wins INTEGER DEFAULT 0, lost INTEGER DEFAULT 0, cage CHAR, kits CHAR)");
		$this->db->exec("CREATE TABLE IF NOT EXISTS lobby(lobbyX INTEGER DEFAULT 0, lobbyY INTEGER DEFAULT 0, lobbyZ INTEGER DEFAULT 0, worldName VARCHAR(124) NOT NULL)");
		$this->sqlCreateNewData = $this->db->prepare("INSERT INTO players(playerName) VALUES (:playerName)");
		$this->sqlGetPlayerData = $this->db->prepare("SELECT * FROM players WHERE playerName = :player");
		$this->sqlUpdateNewData = $this->db->prepare("UPDATE players SET playerName = :playerName, playerTime = :playerTime, kills = :kills, deaths = :deaths, wins = :wins, lost = :lost, cage = :cage, kits = :kits WHERE playerName = :playerName");

		$this->sqlGetLobbyPos = $this->db->prepare("SELECT * FROM lobby WHERE worldName IS NOT NULL");
		$this->sqlGetLobbyInsert = $this->db->prepare("INSERT INTO lobby(lobbyX, lobbyY, lobbyZ, worldName) VALUES (:lobbyX, :lobbyY, :lobbyZ, :worldName)");
		$this->sqlGetLobbyUpdate = $this->db->prepare("UPDATE lobby SET lobbyX = :lobbyX, lobbyY= :lobbyY, lobbyZ= :lobbyZ, worldName = :worldName WHERE worldName = :worldNameData");
	}

	public function close(){
		// Close this shit databases
		$this->sqlCreateNewData->close();
		$this->sqlGetPlayerData->close();
		$this->sqlUpdateNewData->close();
		$this->sqlGetLobbyPos->close();
		$this->sqlGetLobbyInsert->close();
		$this->sqlGetLobbyUpdate->close();

		$this->db->close();

		// Then unset them
		unset($this->sqlCreateNewData, $this->sqlGetPlayerData, $this->sqlUpdateNewData,
			$this->sqlGetLobbyPos, $this->sqlGetLobbyInsert, $this->sqlGetLobbyUpdate);
		Server::getInstance()->getLogger()->info(SkyWarsPE::getInstance()->getPrefix() . "§aSuccessfully closed the sqlite database");
	}

	/**
	 * Create a player data for new users
	 *
	 * @param string $p
	 * @return int The return of value will be <b>TRUE</b> if the player data is doesn't exists
	 * and the data successfully been created, otherwise <b>FALSE</b> will return.
	 */
	public function createNewData(string $p): int{
		if($this->getPlayerData($p) !== self::DATA_EXECUTE_EMPTY){
			return self::DATA_ALREADY_AVAILABLE;
		}

		$stmt = $this->sqlCreateNewData;
		$stmt->bindValue(":playerName", $p, SQLITE3_TEXT);
		$stmt->reset();
		$result = $this->sqlCreateNewData->execute();

		return $result !== false ? self::DATA_EXECUTE_SUCCESS : self::DATA_EXECUTE_FAILED;
	}

	/**
	 * @param string $p
	 * @return int|PlayerData
	 */
	public function getPlayerData(string $p){
		$stmt = $this->sqlGetPlayerData;
		$stmt->bindValue(":player", $p, SQLITE3_TEXT);
		$stmt->reset();
		$result = $stmt->execute();
		if($result === false){
			return self::DATA_EXECUTE_FAILED;
		}

		$database = $result->fetchArray(SQLITE3_ASSOC);
		if(empty($database)){
			return self::DATA_EXECUTE_EMPTY;
		}

		$data = new PlayerData();
		$data->player = $database['playerName'];
		$data->time = $database['playerTime'];
		$data->kill = $database['kills'];
		$data->death = $database['deaths'];
		$data->wins = $database['wins'];
		$data->lost = $database['lost'];
		$data->cages = explode(":", $database['cage']);
		$data->kitId = explode(":", $database['kits']);

		return $data;
	}

	/**
	 * Get all of the players in the database
	 *
	 * @return int|PlayerData[]
	 */
	public function getPlayers(){
		$stmt = $this->db->query("SELECT * FROM players");
		$player = [];
		while($row = $stmt->fetchArray(SQLITE3_ASSOC)){
			$data = new PlayerData();
			$data->player = $row['playerName'];
			$data->time = $row['playerTime'];
			$data->kill = $row['kills'];
			$data->death = $row['deaths'];
			$data->wins = $row['wins'];
			$data->lost = $row['lost'];
			$data->cages = explode(":", $row['cage']);
			$data->kitId = explode(":", $row['kits']);
			$player[] = $data;
		}
		$stmt->finalize();

		return $player;
	}

	/**
	 * Store the player data into database.
	 *
	 * @param string $player The player of the target
	 * @param PlayerData $pd PlayerData of the player
	 * @return int The return of value will be <b>TRUE</b> if the player data is exists
	 * and the data successfully been stored, otherwise <b>FALSE</b> will return.
	 */
	public function setPlayerData(string $player, PlayerData $pd): int{
		if(is_integer($this->getPlayerData($player))){
			return self::DATA_EXECUTE_EMPTY;
		}

		$stmt = $this->sqlUpdateNewData;
		$stmt->bindValue(":playerName", $player, SQLITE3_TEXT);
		$stmt->bindValue(":playerTime", $pd->time, SQLITE3_INTEGER);
		$stmt->bindValue(":kills", $pd->kill, SQLITE3_INTEGER);
		$stmt->bindValue(":deaths", $pd->death, SQLITE3_INTEGER);
		$stmt->bindValue(":wins", $pd->wins, SQLITE3_INTEGER);
		$stmt->bindValue(":lost", $pd->lost, SQLITE3_INTEGER);
		$stmt->bindValue(":cage", implode(":", $pd->cages), SQLITE3_TEXT);
		$stmt->bindValue(":kits", implode(":", $pd->kitId), SQLITE3_TEXT);
		$stmt->reset();
		$result = $stmt->execute();

		return $result !== false ? self::DATA_EXECUTE_SUCCESS : self::DATA_EXECUTE_FAILED;
	}

	/**
	 * Set the position of the current lobby position into
	 * database
	 *
	 * @param Position $pos
	 * @return int
	 */
	public function setLobby(Position $pos): int{
		$node = $this->getLobby();

		if($node === self::DATA_EXECUTE_FAILED){
			return self::DATA_EXECUTE_FAILED;
		}elseif($node === self::DATA_EXECUTE_EMPTY){
			$stmt = $this->sqlGetLobbyInsert;
		}else{
			$stmt = $this->sqlGetLobbyUpdate;
			$stmt->bindValue(":worldNameData", $node->getLevel()->getName());
		}

		$stmt->bindValue(":lobbyX", $pos->getFloorX(), SQLITE3_INTEGER);
		$stmt->bindValue(":lobbyY", $pos->getFloorY(), SQLITE3_INTEGER);
		$stmt->bindValue(":lobbyZ", $pos->getFloorZ(), SQLITE3_INTEGER);
		$stmt->bindValue(":worldName", $pos->getLevel()->getName(), SQLITE3_TEXT);
		$stmt->reset();
		$result = $stmt->execute();
		if($result !== false){
			$this->positionCache = $pos;

			return true;
		}

		return false;

	}

	/**
	 * Get the lobby for the SkyWars game
	 *
	 * @return int|Position
	 */
	public function getLobby(){
		# Check if the lobby location is already in cache
		if(isset($this->positionCache)){
			return $this->positionCache;
		}
		# Execute the database and search for a position
		$stmt = $this->sqlGetLobbyPos;
		$stmt->reset();
		$result = $stmt->execute();
		# Failure? How this possibly happens
		# First time usage?
		if($result === false){
			return self::DATA_EXECUTE_FAILED;
		}
		$database = $result->fetchArray(SQLITE3_ASSOC);
		# The database is empty, however its might be doing with
		# First time usage?
		if(empty($database)){
			return self::DATA_EXECUTE_EMPTY;
		}
		# Load the world first then get the level and return to this position
		Utils::loadFirst($database['worldName']);
		$level = Server::getInstance()->getLevelByName($database['worldName']);
		$pos = new Position($database['lobbyX'], $database['lobbyY'], $database['lobbyZ'], $level);
		$this->positionCache = $pos;

		return $pos;
	}

}