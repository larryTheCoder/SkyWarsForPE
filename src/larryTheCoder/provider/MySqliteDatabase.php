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

/**
 * TODO Mysql connection
 *
 * Class MySqliteDatabase
 * @package larryTheCoder\provider
 */
class MySqliteDatabase extends SkyWarsDatabase {

	/** @var \mysqli */
	private $db;
	/** @var \mysqli_stmt */
	private $sqlCreateNewData, $sqlGetPlayerData, $sqlUpdateNewData, $sqlGetLobbyPos, $sqlGetLobbyInsert, $sqlGetLobbyUpdate;
	/** @var Position */
	private $lobbyPos;

	public function __construct(SkyWarsPE $plugin){
		ini_set("mysqli.reconnect", 1);
		ini_set('mysqli.allow_persistent', 1);
		ini_set('mysql.connect_timeout', 300);
		ini_set('default_socket_timeout', 300);
		parent::__construct($plugin);
		$this->init();
		$this->logger->info($plugin->getPrefix() . "§aConnected to mysql server");
	}

	private function init(){
		$this->db = new \mysqli(Settings::$mysqlHost, Settings::$mysqlUser, Settings::$mysqlPassword, Settings::$mysqlDatabase, Settings::$mysqlPort);
		$this->db->query("CREATE TABLE IF NOT EXISTS players(playerName VARCHAR(64) NOT NULL, playerTime INTEGER DEFAULT 0, kills INTEGER DEFAULT 0, deaths INTEGER DEFAULT 0, wins INTEGER DEFAULT 0, lost INTEGER DEFAULT 0)");
		$this->prepare();
	}

	private function prepare(){
		$this->sqlCreateNewData = $this->db->prepare("INSERT INTO players(playerName) VALUES (?);");
		$this->sqlGetPlayerData = $this->db->prepare("SELECT * FROM players WHERE playerName = ?;");
		$this->sqlUpdateNewData = $this->db->prepare("UPDATE players SET playerName = ?, playerTime = ?, kills = ?, deaths = ?, wins = ?, lost = ?;");

		$this->sqlGetLobbyPos = $this->db->prepare("SELECT * FROM lobby WHERE worldName IS NOT NULL;");
		$this->sqlGetLobbyInsert = $this->db->prepare("INSERT INTO lobby(lobbyX, lobbyY, lobbyZ, worldName) VALUES (?, ?, ?, ?);");
		$this->sqlGetLobbyUpdate = $this->db->prepare("UPDATE lobby SET lobbyX = ?, lobbyY = ?, lobbyZ = ?, worldName = ? WHERE worldName IS NOT NULL;");
	}

	public function createNewData(string $player): int{
		$attempt = $this->reconnect();
		if($attempt === false){
			return self::DATA_EXECUTE_FAILED;
		}

		if($this->getPlayerData($player) !== self::DATA_EXECUTE_EMPTY){
			return self::DATA_ALREADY_AVAILABLE;
		}

		$stmt = $this->sqlCreateNewData;
		$stmt->reset();
		$stmt->bind_param("s", $player);
		$result = $stmt->execute();
		if($result === false){
			$this->logger->error($stmt->error);

			return self::DATA_EXECUTE_FAILED;
		}

		return self::DATA_EXECUTE_SUCCESS;
	}

	private function reconnect(): bool{
		if(!$this->db->ping()){
			$this->getSW()->getLogger()->error("The MySQL server can not be reached! Trying to reconnect!");
			$this->db->close();
			$this->db->connect(Settings::$mysqlHost, Settings::$mysqlUser, Settings::$mysqlPassword, Settings::$mysqlDatabase, Settings::$mysqlPort);
			$this->prepare();
			if($this->db->ping()){
				$this->getSW()->getLogger()->notice("The MySQL connection has been re-established!");

				return true;
			}else{
				$this->getSW()->getLogger()->critical("The MySQL connection could not be re-established!");
				$this->getSW()->getLogger()->critical("Cannot save player data! Please be caution.");

				return false;
			}
		}

		return true;
	}

	public function getPlayerData(string $player){
		$attempt = $this->reconnect();
		if($attempt === false){
			return self::DATA_EXECUTE_FAILED;
		}

		$stmt = $this->sqlGetPlayerData;
		$stmt->reset();
		$stmt->bind_param("s", $player);
		$result = $stmt->execute();
		if($result === false){
			$this->logger->error($stmt->error);

			return self::DATA_EXECUTE_FAILED;
		}
		$result = $stmt->get_result();
		while($val = $result->fetch_array(MYSQLI_ASSOC)){
			$data = new PlayerData();
			$data->player = $val['playerName'];
			$data->time = $val['playerTime'];
			$data->kill = $val['kills'];
			$data->death = $val['deaths'];
			$data->wins = $val['wins'];
			$data->lost = $val['lost'];

			return $data;
		}

		return self::DATA_EXECUTE_EMPTY;
	}

	public function setPlayerData(string $p, PlayerData $pd): int{
		$attempt = $this->reconnect();
		if($attempt === false){
			return self::DATA_EXECUTE_FAILED;
		}

		if(!is_integer($this->getPlayerData($p))){
			return self::DATA_EXECUTE_EMPTY;
		}

		$stmt = $this->sqlUpdateNewData;
		$stmt->reset();
		$stmt->bind_param("siiiii", $p, $pd->time, $pd->kill, $pd->death, $pd->wins, $pd->lost);
		$result = $stmt->execute();
		if($result === false){
			$this->getSW()->getLogger()->error($stmt->error);

			return self::DATA_EXECUTE_FAILED;
		}

		return self::DATA_EXECUTE_SUCCESS;
	}

	public function close(): void{
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
	}

	public function getLobby(): Position{
		if(isset($this->lobbyPos)){
			return $this->lobbyPos;

		}
		$this->reconnect();

		# Prepare the sql database
		$stmt = $this->sqlGetLobbyPos;
		$result = $stmt->get_result();
		while($val = $result->fetch_array()){
			# There is a data, load them and set the position
			Utils::loadFirst($val["worldName"]);
			$level = Server::getInstance()->getLevelByName($val['worldName']);
			$data = new Position($val["lobbyX"], $val["lobbyY"], $val["lobbyZ"], $level);
			$this->lobbyPos = $data;

			return $data;
		}
		# Not in database... We set a new one
		$default = Server::getInstance()->getDefaultLevel()->getSpawnLocation();
		$this->setLobby($default);

		return $default;
	}

	public function setLobby(Position $pos): int{
		$this->reconnect();

		# Get if the database had a data
		$stmt = $this->sqlGetLobbyPos;
		$result = $stmt->get_result();
		if(!empty($result)){
			$stmt = $this->sqlGetLobbyUpdate;
		}else{
			$stmt = $this->sqlGetLobbyInsert;
		}
		# Bind the parameters which the same
		$stmt->bind_param("iiis", $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $pos->getLevel()->getName());
		$result = $stmt->execute();
		if($result === false){
			$this->getSW()->getLogger()->error($stmt->error);

			return false;
		}
		$this->lobbyPos = $pos;

		return true;
	}

	/**
	 * Get the players data
	 */
	public function getPlayers(){
		$stmt = $this->db->query("SELECT * FROM players");
		$player = [];
		while($row = $stmt->fetch_array(SQLITE3_ASSOC)){
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
		$stmt->close();

		return $player;
	}
}