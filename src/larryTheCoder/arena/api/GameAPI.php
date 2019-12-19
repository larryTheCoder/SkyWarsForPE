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

namespace larryTheCoder\arena\api;


use larryTheCoder\arena\Arena;
use pocketmine\event\HandlerList;
use pocketmine\Player;

/**
 * GameAPI, a class that holds the information  about how the arena behave towards their players.
 * This is useful when you want to change some of the settings that been set within this code.
 * Make it more useful and fun instead of a plain core of skywars itself.
 *
 * @package larryTheCoder\arena\api
 */
abstract class GameAPI {

	/** @var Arena */
	public $arena;

	public function __construct(Arena $arena){
		$arena->gameAPICodename = $this->getCodeName();
		$this->arena = $arena;
	}

	/**
	 * The API codename.
	 *
	 * @return string
	 */
	public function getCodeName(){
		return "SkyWars-Classic";
	}

	/**
	 * Start the arena, begin the match in the
	 * arena provided.
	 */
	public abstract function startArena(): void;

	/**
	 * Stop the arena, rollback to defaults and
	 * reset the arena if possible.
	 */
	public abstract function stopArena(): void;

	/**
	 * Called when a player joins into the arena
	 *
	 * @param Player $p
	 * @return bool
	 */
	public abstract function joinToArena(Player $p): bool;

	/**
	 * Called when a player leaves the arena.
	 *
	 * @param Player $p
	 * @param bool $force
	 * @return bool
	 */
	public abstract function leaveArena(Player $p, bool $force = false): bool;

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1s
	 *
	 * @return array
	 */
	public abstract function getRuntimeTasks(): array;

	/**
	 * Do something when the code is trying to remove every players
	 * from the list.
	 */
	public abstract function removeAllPlayers();

	/**
	 * Shutdown this API from using this arena.
	 * You may found this a very useful function.
	 */
	public function shutdown(): void{
		HandlerList::unregisterAll($this);
	}
}