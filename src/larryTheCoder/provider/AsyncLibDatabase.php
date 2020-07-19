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
use pocketmine\level\Position;

/**
 * A better asynchronous database operations for SkyWars objects.
 * Uses libasyncsql in order to work properly.
 *
 * @package larryTheCoder\provider
 */
class AsyncLibDatabase {

	public function close(){
		// TODO: Implement close() method.
	}

	public function createNewData(string $p): int{
		// TODO: Implement createNewData() method.
	}

	public function getPlayerData(string $p, callable $objectReturned){
		// TODO: Implement getPlayerData() method.
	}

	public function setPlayerData(string $p, PlayerData $pd): int{
		// TODO: Implement setPlayerData() method.
	}

	public function getPlayers(){
		// TODO: Implement getPlayers() method.
	}

	public function getLobby(){
		// TODO: Implement getLobby() method.
	}

	public function setLobby(Position $pos): int{
		// TODO: Implement setLobby() method.
	}
}