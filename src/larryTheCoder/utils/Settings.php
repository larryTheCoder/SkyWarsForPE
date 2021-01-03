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

namespace larryTheCoder\utils;

use larryTheCoder\arena\api\SignManager;
use pocketmine\Server;
use pocketmine\utils\Config;

class Settings {

	# ============ GENERALS CONFIG ============

	/** @var string */
	public static $prefix = '§d[§eSkyWars§d] §a';
	/** @var int */
	public static $joinHealth = 20;
	/** @var string[] */
	public static $acceptedCommand = [];
	/** @var int */
	public static $defaultGamemode = 0;

	# ============ GENERALS CONFIG ============

	public final static function init(Config $config): void{
		# ============ GENERALS CONFIG ============

		$general = $config->get("general");
		self::$prefix = str_replace("&", "§", $general['prefix']);
		self::$joinHealth = $general['join-health'];
		self::$acceptedCommand = array_merge(explode(":", $general['accepted-cmd']), ['sw', 'skywars']);
		self::$defaultGamemode = $general['default-gamemode'] ?? 0;
		SignManager::$blockStatus = (bool)$general['enable-block-status'];

		# ============ GENERALS CONFIG ============

		Server::getInstance()->getLogger()->info(Settings::$prefix . "§aLoaded SkyWars configuration into system.");
	}

}