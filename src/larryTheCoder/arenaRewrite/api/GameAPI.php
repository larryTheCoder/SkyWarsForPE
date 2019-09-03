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

namespace larryTheCoder\arenaRewrite\api;


use larryTheCoder\arenaRewrite\Arena;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;

/**
 * GameAPI, a class that holds the information  about how the arena behave towards their players.
 * This is useful when you want to change some of the settings that been set within this code.
 * Make it more useful and fun instead of a plain core of skywars itself.
 *
 * @package larryTheCoder\arenaRewrite\api
 */
abstract class GameAPI implements Listener {

	/** @var Arena */
	public $arena;

	public function __construct(Arena $arena){
		$arena->gameAPICodename = $this->getCodeName();
		$this->arena = $arena;

		Server::getInstance()->getPluginManager()->registerEvents($this, SkyWarsPE::getInstance());
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
	 * @return bool
	 */
	public abstract function leaveArena(Player $p): bool;

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1s
	 *
	 * @return array
	 */
	public abstract function getRuntimeTasks(): array;
}