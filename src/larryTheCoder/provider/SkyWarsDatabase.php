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
use pocketmine\level\Position;
use pocketmine\utils\MainLogger;

abstract class SkyWarsDatabase {

	const DATA_EXECUTE_SUCCESS = 4;
	const DATA_ALREADY_AVAILABLE = 2;
	const DATA_EXECUTE_FAILED = 1;
	const DATA_EXECUTE_EMPTY = 0;

	/** @var MainLogger */
	protected $logger;
	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;
		$this->logger = $plugin->getServer()->getLogger();
	}

	public function getSW(): SkyWarsPE{
		return $this->plugin;
	}

	/**
	 * Close the current connection with database
	 */
	public abstract function close();

	/**
	 * Create a player data for new users
	 *
	 * @param string $p
	 * @return int The return of value will be <b>TRUE</b> if the player data is doesn't exists
	 * and the data successfully been created, otherwise <b>FALSE</b> will return.
	 */
	public abstract function createNewData(string $p): int;

	/**
	 * Get the player data for the game-play
	 *
	 * @param string $p
	 * @return int|PlayerData
	 */
	public abstract function getPlayerData(string $p);

	/**
	 * Store the player data into database.
	 *
	 * @param string $p The player of the target
	 * @param PlayerData $pd PlayerData of the player
	 * @return int The return of value will be <b>TRUE</b> if the player data is exists
	 * and the data successfully been stored, otherwise <b>FALSE</b> will return.
	 */
	public abstract function setPlayerData(string $p, PlayerData $pd): int;

	/**
	 * Get the players data
	 *
	 * @return int|PlayerData[]
	 */
	public abstract function getPlayers();

	/**
	 * Get the lobby for the SkyWars game
	 *
	 * @return int|Position
	 */
	public abstract function getLobby();

	/**
	 * Set the position of the current lobby position into
	 * database
	 *
	 * @param Position $pos
	 * @return int
	 */
	public abstract function setLobby(Position $pos): int;

}