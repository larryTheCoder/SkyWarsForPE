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


use larryTheCoder\SkyWarsPE;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;
use pocketmine\utils\{
	Config, TextFormat
};

class Settings {

	# ============ GENERALS CONFIG ============

	/** @var string */
	public static $lang = "";
	/** @var string */
	public static $prefix = "";
	/** @var bool */
	public static $useEconomy = true;
	/** @var bool */
	public static $useKits = true;
	/** @var int */
	public static $joinHealth = 20;
	/** @var string[] */
	public static $acceptedCommand = [];
	/** @var bool */
	public static $startWhenFull = true;
	/** @var bool */
	public static $itemInteract = true;
	/** @var bool */
	public static $zipCompression = true;
	/** @var bool */
	public static $zipArchive = true;
	/** @var int */
	public static $typeMessageSendArena = 0;
	/** @var bool */
	public static $isModded = false;

	# ============ GENERALS CONFIG ============

	# ============== CHAT CONFIG ==============

	/** @var bool */
	public static $enableCListener = true;
	/** @var string */
	public static $chatFormatPlayer = "§a%1 §0-> §f%2";
	/** @var string */
	public static $chatFormatSpectator = "§7[DEAD] %1 §0-> §7%2";
	/** @var bool */
	public static $chatSpy = true;

	# ============== CHAT CONFIG ==============

	# ============ DATABASE CONFIG ============

	/** @var string */
	public static $selectedDatabase = "";
	/** @var string */
	public static $mysqlHost = "";
	/** @var string */
	public static $mysqlPort = "";
	/** @var string */
	public static $mysqlUser = "";
	/** @var string */
	public static $mysqlPassword = "";
	/** @var string */
	public static $mysqlDatabase = "";
	/** @var string */
	public static $mysqlPrefix = "";
	/** @var string */
	public static $sqlitePath = "";

	# ============ DATABASE CONFIG ============

	# ============== ITEM CONFIG ==============

	/** @var bool */
	public static $enableSpecialItem = false;
	/** @var bool */
	public static $enableDoubleTap = false;
	/** @var int */
	public static $doubleTapInterval = 0;
	/** @var array */
	public static $items = [];

	# ============== ITEM CONFIG ==============

	public final static function init(Config $config){
		# ============ GENERALS CONFIG ============

		$general = $config->get("general");
		self::$lang = $general['language'];
		self::$prefix = str_replace("&", "§", $general['prefix']);
		self::$useEconomy = $general['use-economy'];
		self::$useKits = $general['use-kits'];
		self::$joinHealth = $general['join-health'];
		self::$acceptedCommand = explode(":", $general['accepted-cmd']);
		self::$startWhenFull = $general['start-when-full'];
		self::$itemInteract = $general['item-interact'];
		self::$zipCompression = $general['zip-compression'];
		self::$zipArchive = $general['zip-archive'];
		self::$isModded = $general['isModded'];

		# ============ GENERALS CONFIG ============

		# ============== CHAT CONFIG ==============

		$chat = $config->get("chat");
		self::$enableCListener = $chat["chat-listener"];
		self::$chatFormatPlayer = str_replace("&", "§", $chat["chat-format-player"]);
		self::$chatFormatSpectator = str_replace("&", "§", $chat["chat-format-spectate"]);
		self::$chatSpy = $chat["chat-spy"];

		# ============== CHAT CONFIG ==============

		# ============ DATABASE CONFIG ============

		$database = $config->get("database");
		self::$selectedDatabase = $database["selected-database"];
		# Mysql Configuration
		self::$mysqlHost = $database["mysql"]["hostname"];
		self::$mysqlPort = $database["mysql"]["port"];
		self::$mysqlUser = $database["mysql"]["user"];
		self::$mysqlPrefix = $database["mysql"]["prefix"];
		self::$mysqlDatabase = $database["mysql"]["database"];
		self::$mysqlPassword = $database["mysql"]["password"];
		# Sqlite Configuration
		self::$sqlitePath = str_replace(["%1", "%2"], [Server::getInstance()->getDataPath(), SkyWarsPE::getInstance()->getDataFolder()], $database["sqlite"]["path"]);

		# ============ DATABASE CONFIG ============

		# ============== ITEM CONFIG ==============

		$item = $config->get("item");
		self::$enableSpecialItem = $item['enable-special-item'];
		self::$enableDoubleTap = $item['enable-double-tap'];
		self::$doubleTapInterval = $item['double-tap-interval'];
		foreach(array_keys($item) as $key){
			# Check if the item contains a `item-id`
			# Which its is important
			if(isset($item[$key]['item-id']) && !isset(self::$items[$key])){
				$data = explode(":", $item[$key]['item-id']);
				$toItem = Item::get($data[0], $data[1]);
				$toItem->setCustomName(str_replace("&", "§", "&r" . $item[$key]['item-name']) . "\n§e(Right Click)");
				$placeAt = !isset($item[$key]['item-place']) ? 0 : $item[$key]['item-place'];
				$itemCmd = !isset($item[$key]['item-cmd']) ? "" : $item[$key]['item-cmd'];
				$showItemAt = !isset($item[$key]['show-after-win']) ? true : $item[$key]['show-after-win'];
				$itemPermission = !isset($item[$key]['item-permission']) ? "" : $itemPermission = $item[$key]['item-permission'];
				$itemSpectate = !isset($item[$key]['item-spectate']) ? false : $itemSpectate = $item[$key]['item-spectate'];
				$itemBypass = !isset($item[$key]['bypass-double-tap']) ? false : $item[$key]['bypass-double-tap'];
				$itemDoubleMessage = !isset($item[$key]['double-tap-message']) ? "&aTap again to confirm" : $item[$key]['double-tap-message'];
				$nbt = $toItem->getNamedTag() === null ? new CompoundTag() : $toItem->getNamedTag();
				$nbt->setString("command", $itemCmd);
				$toItem->setCompoundTag($nbt);

				self::$items[TextFormat::clean($toItem->getCustomName())] = [$toItem, $placeAt, $itemPermission, $itemSpectate, $itemBypass, $itemDoubleMessage, $showItemAt];
			}
		}

		# ============== ITEM CONFIG ==============

		Server::getInstance()->getLogger()->info(SkyWarsPE::getInstance()->getPrefix() . "§aLoaded SkyWars configuration into system.");
	}

}