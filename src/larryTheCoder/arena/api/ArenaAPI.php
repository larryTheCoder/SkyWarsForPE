<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2019 larryTheCoder and contributors
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

use pocketmine\math\Vector3;
use pocketmine\Player;

/**
 * ArenaAPI, a class that holds the information about how the arena behave towards their players.
 * This is useful when you want to change some of the settings that been set within this code.
 * Make it more useful and fun instead of a plain core of skywars itself.
 *
 * @package larryTheCoder\arena\api
 */
interface ArenaAPI {

	/**
	 * The API codename.
	 *
	 * @return string
	 */
	public function getCodeName(): string;

	/**
	 * Start the arena, begin the match in the
	 * arena provided.
	 */
	public function startArena(): void;

	/**
	 * Stop the arena, rollback to defaults and
	 * reset the arena if possible.
	 */
	public function stopArena(): void;

	/**
	 * Called when a player joins into the arena
	 *
	 * @param Player $p
	 * @param Vector3 $position
	 * @return bool
	 */
	public function joinToArena(Player $p, Vector3 $position): bool;

	/**
	 * Called when a player leaves the arena.
	 *
	 * @param Player $p
	 * @param bool $force
	 * @return bool
	 */
	public function leaveArena(Player $p, bool $force = false): bool;

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1s
	 *
	 * @return ArenaTask[]
	 */
	public function getRuntimeTasks(): array;

	/**
	 * Do something when the code is trying to remove every players
	 * from the list.
	 */
	public function removeAllPlayers();

	/**
	 * Shutdown this API from using this arena.
	 * You may found this a very useful function.
	 */
	public function shutdown(): void;

	/**
	 * @return ArenaListener
	 */
	public function getEventListener(): ArenaListener;
}