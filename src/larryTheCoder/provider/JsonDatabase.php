<?php
/**
 *  BSD 3-Clause License
 *
 *  Copyright (c) 2019, larryTheCoder
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 *  - Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 *  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 *  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 *  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 *  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 *  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 *  CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 *  OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 *  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace larryTheCoder\provider;


use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\level\Position;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;

class JsonDatabase extends SkyWarsDatabase {

	const LOBBY_DATABASE = "lobby.json";

	/** @var Config */
	private $lobbyDatabase;

	public function __construct(SkyWarsPE $plugin){
		parent::__construct($plugin);
		$this->lobbyDatabase = new Config(Settings::$jsonPath . "/" . self::LOBBY_DATABASE, Config::JSON);

		$this->logger->info($plugin->getPrefix() . "§bSuccessfully loaded JSON Databases");
	}

	/**
	 * Close the current connection with database
	 */
	public function close(){
		$this->lobbyDatabase->save();
	}

	/**
	 * Create a player data for new users
	 *
	 * @param string $p
	 * @return int The return of value will be <b>TRUE</b> if the player data is doesn't exists
	 * and the data successfully been created, otherwise <b>FALSE</b> will return.
	 */
	public function createNewData(string $p): int{
		$path = new Config($this->getPlayerPath($p));
		if(empty($path->getAll())){
			$path->setAll([
				"playerName" => $p,
				"playerTime" => 0,
				"kills"      => 0,
				"wins"       => 0,
				"lost"       => 0,
				"cage"       => [],
				"kits"       => [],
			]);

			return $path->save() ? self::DATA_EXECUTE_SUCCESS : self::DATA_EXECUTE_FAILED;
		}

		return self::DATA_EXECUTE_SUCCESS;
	}

	/**
	 * Get the player data for the game-play
	 *
	 * @param string $p
	 * @return int|PlayerData
	 */
	public function getPlayerData(string $p){
		if(is_file($this->getPlayerPath($p))){
			return self::DATA_EXECUTE_EMPTY;
		}

		$config = new Config($this->getPlayerPath($p));

		return $this->getFragmentData($config->getAll());
	}

	/**
	 * Store the player data into database.
	 *
	 * @param string $p The player of the target
	 * @param PlayerData $pd PlayerData of the player
	 * @return int The return of value will be <b>TRUE</b> if the player data is exists
	 * and the data successfully been stored, otherwise <b>FALSE</b> will return.
	 */
	public function setPlayerData(string $p, PlayerData $pd): int{
		if(is_file($this->getPlayerPath($p))){
			return self::DATA_EXECUTE_EMPTY;
		}

		$config = new Config($this->getPlayerPath($p), Config::JSON);
		$config->setAll([
			"playerName" => $pd->player,
			"playerTime" => $pd->time,
			"kills"      => $pd->kill,
			"wins"       => $pd->wins,
			"lost"       => $pd->lost,
			"cage"       => implode(":", $pd->cages),
			"kits"       => implode(":", $pd->kitId),
		]);

		return $config->save() ? self::DATA_EXECUTE_SUCCESS : self::DATA_EXECUTE_FAILED;
	}

	/**
	 * Get the players data
	 *
	 * @return int|PlayerData[]
	 */
	public function getPlayers(){
		$iterator = [];

		foreach(glob(Settings::$jsonPath . "*.json") as $file){
			$playerData = new Config($file, Config::JSON);

			$iterator[] = $this->getFragmentData($playerData->getAll());
		}

		return $iterator;
	}

	private function getFragmentData(array $cfData): PlayerData{
		$data = new PlayerData();
		$data->player = $cfData['playerName'];
		$data->time = $cfData['playerTime'];
		$data->kill = $cfData['kills'];
		$data->death = $cfData['deaths'];
		$data->wins = $cfData['wins'];
		$data->lost = $cfData['lost'];
		$data->cages = explode(":", $cfData['cage']);
		$data->kitId = explode(":", $cfData['kits']);

		return $data;
	}

	/**
	 * Get the lobby for the SkyWars game
	 *
	 * @return int|Position
	 */
	public function getLobby(){
		$data = $this->lobbyDatabase->getAll();
		if(empty($data)){
			skipPosition:
			$pos = Server::getInstance()->getDefaultLevel()->getSpawnLocation();

			$this->lobbyDatabase->setAll([
				"lobbyX"     => $pos->getFloorX(),
				"lobbyY"     => $pos->getFloorY(),
				"lobbyZ"     => $pos->getFloorZ(),
				"lobbyWorld" => $pos->getLevel()->getName(),
			]);
			$this->lobbyDatabase->save();

			return $pos;
		}

		// Warn to console about invalid world names
		$level = Server::getInstance()->getLevelByName($data["lobbyWorld"]);
		if($level === null){
			Utils::send(TF::RED . "Level " . $data["lobbyWorld"] . " does not exists! Using default world");

			goto skipPosition;
		}

		return new Position(intval($data["lobbyX"]), intval($data["lobbyY"]), intval($data["lobbyZ"]), $level);
	}

	/**
	 * Set the position of the current lobby position into
	 * database
	 *
	 * @param Position $pos
	 * @return int
	 */
	public function setLobby(Position $pos): int{
		$this->lobbyDatabase->setAll([
			"lobbyX"     => $pos->getFloorX(),
			"lobbyY"     => $pos->getFloorY(),
			"lobbyZ"     => $pos->getFloorZ(),
			"lobbyWorld" => $pos->getLevel()->getName(),
		]);

		return $this->lobbyDatabase->save() ? self::DATA_EXECUTE_SUCCESS : self::DATA_EXECUTE_FAILED;
	}

	private function getPlayerPath(string $p){
		return Settings::$jsonPath . "/players/$p.json";
	}
}