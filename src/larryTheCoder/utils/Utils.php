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

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\fireworks\entity\FireworksRocket;
use larryTheCoder\utils\fireworks\Fireworks;
use pocketmine\{network\mcpe\protocol\AddActorPacket,
	Player,
	Server,
	utils\MainLogger,
	utils\Random,
	utils\TextFormat,
	utils\TextFormat as VS};
use pocketmine\block\{Block, BlockIds, StainedGlass};
use pocketmine\entity\Entity;
use pocketmine\item\{Item, ItemIds};
use pocketmine\level\{Level, Location, particle\PortalParticle, Position};
use pocketmine\math\Vector3;
use pocketmine\utils\Config;

/**
 * Public general utils class for SkyWars
 * Copyrights Adam Matthew
 *
 * @package larryTheCoder\utils
 */
class Utils {

	/** @var integer[] */
	public static $particleTimer = [];
	/** @var integer[][] */
	public static $helixMathMap = [];
	/** @var Config */
	private static $scoreboard;

	public static function sendDebug(string $log){
		MainLogger::getLogger()->debug("SW-DEBUG: " . $log);
	}

	public static function send(string $string){
		MainLogger::getLogger()->info(Settings::$prefix . TextFormat::GRAY . $string);
	}

	public static function getParticleTimer(int $id){
		return Utils::$particleTimer[$id];
	}

	public static function setParticleTimer(int $id, int $i){
		Utils::$particleTimer[$id] = $i;
	}

	public static function getLocation(int $i, int $width, Position $location): Location{
		$x = $width * cos(($i + 1) * 11.25 * M_PI / 180.0) + $location->getX();
		$z = $width * sin(($i + 1) * 11.25 * M_PI / 180.0) + $location->getZ();

		return new Location($x, $location->getY(), $z, 0, 0, $location->getLevel());
	}

	public static function getLocation2(int $i, int $width, Position $location): Location{
		$x = $width * cos(($i + 16 - 31) * 11.25 * M_PI / 180.0) + $location->getX();
		$z = $width * sin(($i + 16 - 31) * 11.25 * M_PI / 180.0) + $location->getZ();

		return new Location($x, $location->getY(), $z, 0, 0, $location->getLevel());
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public static function helixMath(int $id){
		try{
			return Utils::$helixMathMap[$id];
		}catch(\Exception $exception){

		}

		return null;
	}

	public static function getBlockStatus(ArenaImpl $arena){
		if(!$arena->arenaEnable || $arena->hasFlags(Arena::ARENA_IN_SETUP_MODE)){
			return new StainedGlass(14);
		}

		if($arena->getStatus() <= ArenaState::STATE_STARTING){
			return new StainedGlass(13);
		}
		if($arena->getPlayerManager()->getPlayersCount() >= $arena->minimumPlayers){
			return new StainedGlass(4);
		}
		if($arena->getStatus() === ArenaState::STATE_ARENA_RUNNING){
			return new StainedGlass(6);
		}
		if($arena->getStatus() === ArenaState::STATE_ARENA_CELEBRATING){
			return new StainedGlass(11);
		}

		return new StainedGlass(0);
	}

	function centerText(array $lines): string{
		// First step, clone lines without colors
		$maximumLines = [];
		foreach($lines as $primaryLine){
			$maximumLines[] = strlen(VS::clean($primaryLine));
		}

		// Now find the maximum lines in the array.
		$stickWith = max($maximumLines);
		$output = "";

		// Check how many lines has been processed
		$currentOutput = 0;
		foreach($lines as $line){
			$currentOutput++;
			$diff = round(($stickWith - strlen(VS::clean($line))) / 2);
			if($diff !== 0){
				$line = str_repeat(" ", intval($diff)) . $line;
			}

			if($currentOutput === count($lines)){
				$output .= "{$line}";
			}else{
				$output .= "{$line}\n";
			}
		}

		return $output;
	}

	/**
	 * @param int $id
	 * @param int $rotation
	 * @param int $up
	 */
	public static function setHelixMath(int $id, int $rotation, int $up){
		Utils::$helixMathMap[$id] = [$rotation, $up];
	}

	public static function addFireworks(Position $pos){
		$data = new Fireworks();
		$data->addExplosion(Fireworks::TYPE_BURST, rand(1, 15), 1, 1);

		$nbt = Entity::createBaseNBT($pos, null, lcg_value() * 360, 90);
		$rocket = new FireworksRocket($pos->getLevel(), $nbt, $data);

		$rocket->spawnToAll();
	}

	/**
	 * @param Player $p
	 */
	public static function strikeLightning(Player $p){
		$level = $p->getLevel();

		$light = new AddActorPacket();
		$light->metadata = [];

		$light->type = AddActorPacket::LEGACY_ID_MAP_BC[93];
		$light->entityRuntimeId = Entity::$entityCount++;
		$light->entityUniqueId = 0;

		$light->position = $p->getPosition();
		$light->motion = new Vector3();

		$light->yaw = $p->getYaw();
		$light->pitch = $p->getPitch();

		Server::getInstance()->broadcastPacket($level->getPlayers(), $light);
	}

	public static function unloadGame(){
		foreach(SkyWarsPE::getInstance()->getArenaManager()->getArenas() as $name => $arena){
			$arena->shutdown();
		}

		SkyWarsPE::getInstance()->getArenaManager()->invalidate();
	}

	public static function loadFirst(string $levelName, bool $load = true){
		if(empty($levelName)) return;

		Server::getInstance()->generateLevel($levelName);
		if($load) Server::getInstance()->loadLevel($levelName);
	}

	public static function ensureDirectory(string $directory = ""){
		if(!file_exists(SkyWarsPE::getInstance()->getDataFolder() . $directory)){
			@mkdir(SkyWarsPE::getInstance()->getDataFolder() . $directory, 0755);
		}
	}

	public static function copyResourceTo($source, $destination){
		// Check for symlinks
		if(is_link($source)){
			return symlink(readlink($source), $destination);
		}

		// Simple copy for a file
		if(is_file($source)){
			return copy($source, $destination);
		}

		// Make destination directory
		if(!is_dir($destination)){
			mkdir($destination);
		}

		// Loop through the folder
		$dir = dir($source);
		while(false !== $entry = $dir->read()){
			// Skip pointers
			if($entry == '.' || $entry == '..'){
				continue;
			}

			// Deep copy directories
			self::copyResourceTo("$source/$entry", "$destination/$entry");
		}

		// Clean up
		$dir->close();

		return true;
	}

	public static function writeLog($message){
		// FORMAT           : [DATE] Message
		// FORMAT FOR ARENA : [DATE] [ARENA]  Message
		// OTHER FORMATS    : [DATE] [TYPE] Message

		Utils::ensureDirectory("logs/");
		$logFile = SkyWarsPE::getInstance()->getDataFolder() . "/logs/logData.txt";

		$timestamp = date("H:i:s");

		$logResource = \fopen($logFile, "ab");
		if(!\is_resource($logResource)){
			throw new \RuntimeException("Couldn't open log file");
		}

		fwrite($logResource, "[$timestamp]" . $message . PHP_EOL);
		\fclose($logResource);
	}

	public static function deleteDirectory($dir){
		if(!file_exists($dir)){
			return true;
		}

		if(!is_dir($dir)){
			return unlink($dir);
		}

		foreach(scandir($dir) as $item){
			if($item == '.' || $item == '..'){
				continue;
			}

			if(!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)){
				return false;
			}

		}

		return rmdir($dir);
	}

	/**
	 * Convert the string to Block class
	 * This also checks the Block class
	 *
	 * @param string $str
	 * @return Block
	 */
	public static function convertToBlock(string $str){
		$b = explode(":", str_replace([" ", "minecraft:"], ["_", ""], trim($str)));
		if(!isset($b[1])){
			$meta = 0;
		}elseif(is_numeric($b[1])){
			$meta = intval($b[1]) & 0xFFFF;
		}else{
			throw new \InvalidArgumentException("Unable to parse \"" . $b[1] . "\" from \"" . $str . "\" as a valid meta value");
		}

		if(is_numeric($b[0])){
			$item = Block::get(((int)$b[0]) & 0xFFFF, $meta);
		}elseif(defined(BlockIds::class . "::" . strtoupper($b[0]))){
			$item = Block::get(constant(BlockIds::class . "::" . strtoupper($b[0])), $meta);
		}else{
			throw new \InvalidArgumentException("Unable to resolve \"" . $str . "\" to a valid item");
		}

		return $item;
	}

	/**
	 * Convert the item from string to item class
	 * This also checks the Block class
	 *
	 * @param string $str
	 * @return Item
	 */
	public static function convertToItem(string $str): Item{
		$b = explode(":", str_replace([" ", "minecraft:"], ["_", ""], trim($str)));
		if(!isset($b[1])){
			$meta = 0;
		}elseif(is_numeric($b[1])){
			$meta = intval($b[1]) & 0xFFFF;
		}else{
			throw new \InvalidArgumentException("Unable to parse \"" . $b[1] . "\" from \"" . $str . "\" as a valid meta value");
		}

		if(is_numeric($b[0])){
			$item = Item::get(((int)$b[0]) & 0xFFFF, $meta);
		}elseif(defined(ItemIds::class . "::" . strtoupper($b[0]))){
			$item = Item::get(constant(ItemIds::class . "::" . strtoupper($b[0])), $meta);
		}elseif(defined(BlockIds::class . "::" . strtoupper($b[0]))){
			$item = Item::get(constant(BlockIds::class . "::" . strtoupper($b[0])), $meta);
		}else{
			throw new \InvalidArgumentException("Unable to resolve \"" . $str . "\" to a valid item");
		}

		return $item;
	}

	/**
	 * Get the chest contents
	 *
	 * @return array<int, array<int, null>> $templates
	 */
	public static function getChestContents(){
		$items = ['armor'     => [[Item::LEATHER_CAP, Item::LEATHER_TUNIC, Item::LEATHER_PANTS, Item::LEATHER_BOOTS], [Item::GOLD_HELMET, Item::GOLD_CHESTPLATE, Item::GOLD_LEGGINGS, Item::GOLD_BOOTS], [Item::CHAIN_HELMET, Item::CHAIN_CHESTPLATE, Item::CHAIN_LEGGINGS, Item::CHAIN_BOOTS], [Item::IRON_HELMET, Item::IRON_CHESTPLATE, Item::IRON_LEGGINGS, Item::IRON_BOOTS], [Item::DIAMOND_HELMET, Item::DIAMOND_CHESTPLATE, Item::DIAMOND_LEGGINGS, Item::DIAMOND_BOOTS]], //WEAPONS
				  'weapon'    => [[Item::WOODEN_SWORD, Item::WOODEN_AXE,], [Item::GOLD_SWORD, Item::GOLD_AXE], [Item::STONE_SWORD, Item::STONE_AXE], [Item::IRON_SWORD, Item::IRON_AXE], [Item::DIAMOND_SWORD, Item::DIAMOND_AXE]], //FOOD
				  'food'      => [[Item::RAW_PORKCHOP, Item::RAW_CHICKEN, Item::MELON_SLICE, Item::COOKIE], [Item::RAW_BEEF, Item::CARROT], [Item::APPLE, Item::GOLDEN_APPLE], [Item::BEETROOT_SOUP, Item::BREAD, Item::BAKED_POTATO], [Item::MUSHROOM_STEW, Item::COOKED_CHICKEN], [Item::COOKED_PORKCHOP, Item::STEAK, Item::PUMPKIN_PIE],], //THROWABLE
				  'throwable' => [[Item::BOW, Item::ARROW], [Item::SNOWBALL], [Item::EGG]], //BLOCKS
				  'block'     => [Item::STONE, Item::WOODEN_PLANKS, Item::COBBLESTONE, Item::DIRT], //OTHER
				  'other'     => [[Item::WOODEN_PICKAXE, Item::GOLD_PICKAXE, Item::STONE_PICKAXE, Item::IRON_PICKAXE, Item::DIAMOND_PICKAXE], [Item::STICK, Item::STRING]]];


		$templates = [];
		for($i = 0; $i < 25; $i++){

			$armorq = mt_rand(0, 1);
			$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
			$armor1 = [$armortype[\mt_rand(0, (\count($armortype) - 1))], 1];
			if($armorq){
				$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
				$armor2 = [$armortype[mt_rand(0, (count($armortype) - 1))], 1];
			}else{
				$armor2 = [0, 1];
			}
			unset($armorq, $armortype);

			$weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
			$weapon = [$weapontype[mt_rand(0, (count($weapontype) - 1))], 1];
			unset($weapontype);

			$ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
			$food = [$ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5)];
			unset($ftype);

			$add = mt_rand(0, 1);
			if($add){
				$tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
				if(count($tr) == 2){
					$throwable1 = [$tr[1], mt_rand(10, 20)];
					$throwable2 = [$tr[0], 1];
				}else{
					$throwable1 = [0, 1];
					$throwable2 = [$tr[0], mt_rand(5, 10)];
				}
				$other = [0, 1];
			}else{
				$throwable1 = [0, 1];
				$throwable2 = [0, 1];
				$ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
				$other = [$ot[mt_rand(0, (count($ot) - 1))], 1];
			}
			unset($add, $tr, $ot);

			$block = [$items['block'][mt_rand(0, (count($items['block']) - 1))], 64];

			$contents = [$armor1, $armor2, $weapon, $food, $throwable1, $throwable2, $block, $other];
			shuffle($contents);
			$fcontents = [mt_rand(1, 2) => array_shift($contents), mt_rand(3, 5) => array_shift($contents), mt_rand(6, 10) => array_shift($contents), mt_rand(11, 15) => array_shift($contents), mt_rand(16, 17) => array_shift($contents), mt_rand(18, 20) => array_shift($contents), mt_rand(21, 25) => array_shift($contents), mt_rand(26, 27) => array_shift($contents),];
			$templates[] = $fcontents;
		}

		shuffle($templates);

		return $templates;
	}

	/**
	 * Shuffle the item for the chest
	 *
	 * @param Item[] $contents
	 * @return Item[]
	 */
	public static function shuffle(array $contents){
		return [mt_rand(1, 2) => array_shift($contents), mt_rand(3, 5) => array_shift($contents), mt_rand(6, 10) => array_shift($contents), mt_rand(11, 15) => array_shift($contents), mt_rand(16, 17) => array_shift($contents), mt_rand(18, 20) => array_shift($contents), mt_rand(21, 25) => array_shift($contents), mt_rand(26, 27) => array_shift($contents)];
	}

	public static function addParticles(Level $level, Vector3 $pos1, $count = 5){
		$particle1 = new PortalParticle($pos1);
		$random = new Random((int)(microtime(true) * 1000) + mt_rand());
		for($i = 0; $i < $count; ++$i){
			$particle1->setComponents($pos1->x + $random->nextSignedFloat() * 280, $pos1->y + $random->nextSignedFloat() * 280, $pos1->z + $random->nextSignedFloat() * 280);
			$level->addParticle($particle1);
		}
	}

	public static function getScoreboardConfig(){
		if(isset(self::$scoreboard)) return self::$scoreboard;

		$scoreboard = new Config(SkyWarsPE::getInstance()->getDataFolder() . "scoreboard.yml");
		if($scoreboard->get("version", 2) < 2){
			$plug = SkyWarsPE::getInstance();

			rename($plug->getDataFolder() . "scoreboard.yml", $plug->getDataFolder() . "scoreboard.yml.old");
			$plug->saveResource("scoreboard.yml", true);
			$scoreboard->reload();

			Utils::send(TextFormat::YELLOW . "Updated scoreboard version to 2");
		}

		return self::$scoreboard = $scoreboard;
	}

	/**
	 * @param int $number
	 * @return string
	 */
	public static function addPrefix(int $number){
		if($number === 1){
			return $number . "st";
		}elseif($number === 2){
			return $number . "nd";
		}elseif($number === 3){
			return $number . "rd";
		}

		return $number . "th";
	}
}
