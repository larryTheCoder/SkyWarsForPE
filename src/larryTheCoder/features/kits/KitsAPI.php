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

namespace larryTheCoder\features\kits;

use pocketmine\event\Event;
use pocketmine\Player;

/**
 * This is a KitsAPI, used to register the Kits to
 * the arena. Used by players. You can extends this
 * class to make a cool kits for this game.
 *
 * @package larryTheCoder\kits
 */
abstract class KitsAPI {

	/**
	 * Get the kit name for the Kit
	 * @return string
	 */
	public abstract function getKitName(): string;

	/**
	 * The price for the kits, depends on the
	 * server if they installed any Economy
	 * plugins
	 *
	 * @return int
	 */
	public abstract function getKitPrice(): int;

	/**
	 * Get the description for the Kit
	 * put 'null' if you don't want them
	 *
	 * @return string
	 */
	public abstract function getDescription(): string;

	/**
	 * Provide to execute this kit/feature. This
	 * kit will be executed when the game has been started.
	 *
	 * @param Player $p
	 */
	public abstract function executeKit(Player $p);

	/**
	 * Start to listen to events in the arena from
	 * this plugin. Its will only be listened to
	 * the player that owns this kit. No need to worry
	 * if the event will be executed trough other player.
	 *
	 * @param Event $event
	 */
	public function eventExecution(Event $event){

	}

}