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


namespace larryTheCoder\events;


use larryTheCoder\arena\Arena;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\{
	Cancellable, plugin\PluginEvent
};
use pocketmine\Player;

/**
 * This event will be called when a player attempted to leave arena
 * when player is in arena that not ended yet by calling '/leave'
 *
 * @package larryTheCoder\events
 */
class PlayerLeaveArenaEvent extends PluginEvent implements Cancellable {
	public static $handlerList = null;
	protected $player;
	protected $arena;

	public function __construct(SkyWarsPE $plugin, Player $player, Arena $arena){
		parent::__construct($plugin);
		$this->player = $player;
		$this->arena = $arena;
	}

	public function getPlayer(){
		return $this->player;
	}

	public function getArena(){
		return $this->arena;
	}

}