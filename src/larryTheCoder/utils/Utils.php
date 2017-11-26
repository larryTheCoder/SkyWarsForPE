<?php

namespace larryTheCoder\utils;

use larryTheCoder\SkyWarsPE;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

/**
 * Public general utils class for SkyWars
 * Copyrights Adam Matthew
 *
 * @package larryTheCoder\utils
 */
class Utils {

	/**
	 * @param Player $p
	 */
	public static function strikeLightning(Player $p) {
		$level = $p->getLevel();

		$light = new AddEntityPacket();
		$light->metadata = [];

		$light->type = 93;
		$light->entityRuntimeId = Entity::$entityCount++;
		$light->entityUniqueId = 0;

		$light->speedX = 0;
		$light->speedY = 0;
		$light->speedZ = 0;

		$light->yaw = $p->getYaw();
		$light->pitch = $p->getPitch();

		$light->x = $p->x;
		$light->y = $p->y;
		$light->z = $p->z;

		Server::getInstance()->broadcastPacket($level->getPlayers(), $light);
	}

	public static function unloadGame() {
		foreach (SkyWarsPE::getInstance()->ins as $arena) {
			if ($arena->game !== 0) {
				$arena->stopGame();
			}
		}
	}

	public static function checkFile(Config $arena) {
		if (!(is_numeric($arena->getNested("signs.join_sign_x")) && is_numeric($arena->getNested("signs.join_sign_y")) && is_numeric($arena->getNested("signs.join_sign_z")) && is_numeric($arena->getNested("arena.max_game_time")) && is_string($arena->getNested("arena.weather")) && is_string($arena->getNested("signs.join_sign_world")) && is_string($arena->getNested("signs.status_line_1")) && is_string($arena->getNested("signs.status_line_2")) && is_string($arena->getNested("signs.status_line_3")) && is_string($arena->getNested("signs.status_line_4")) && is_numeric($arena->getNested("signs.return_sign_x")) && is_numeric($arena->getNested("signs.return_sign_y")) && is_numeric($arena->getNested("signs.return_sign_z")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("chest.refill_rate")) && is_numeric($arena->getNested("arena.spec_spawn_x")) && is_numeric($arena->getNested("arena.spec_spawn_y")) && is_numeric($arena->getNested("arena.spec_spawn_z")) && is_numeric($arena->getNested("arena.max_players")) && is_numeric($arena->getNested("arena.min_players")) && is_numeric($arena->getNested("arena.grace_time")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.starting_time")) && is_array($arena->getNested("arena.spawn_positions")) && is_string($arena->getNested("arena.finish_msg_levels")) && !is_string($arena->getNested("arena.money_reward")))) {
			return false;
		}
		if (!((strtolower($arena->getNested("signs.enable_status")) == "true" || strtolower($arena->getNested("signs.enable_status")) == "false") && (strtolower($arena->getNested("arena.spectator_mode")) == "true" || strtolower($arena->getNested("arena.spectator_mode")) == "false") && (strtolower($arena->getNested("chest.refill")) == "true" || strtolower($arena->getNested("chest.refill")) == "false") && (strtolower($arena->getNested("arena.time")) == "true" || strtolower($arena->getNested("arena.time")) == "day" || strtolower($arena->getNested("arena.time")) == "night" || is_numeric(strtolower($arena->getNested("arena.time")))) && (strtolower($arena->getNested("arena.start_when_full")) == "true" || strtolower($arena->getNested("arena.start_when_full")) == "false") && (strtolower($arena->get("enabled")) == "true" || strtolower($arena->get("enabled")) == "false"))) {
			return false;
		}
		return true;
	}

	public static function copyr($source, $dest) {
		// Check for symlinks
		if (is_link($source)) {
			return symlink(readlink($source), $dest);
		}

		// Simple copy for a file
		if (is_file($source)) {
			return copy($source, $dest);
		}

		// Make destination directory
		if (!is_dir($dest)) {
			mkdir($dest);
		}

		// Loop through the folder
		$dir = dir($source);
		while (false !== $entry = $dir->read()) {
			// Skip pointers
			if ($entry == '.' || $entry == '..') {
				continue;
			}

			// Deep copy directories
			self::copyr("$source/$entry", "$dest/$entry");
		}

		// Clean up
		$dir->close();
		return true;
	}

	/*
	 * Get the chest contents
	 *
	 * @return String[] $templates
	 */

	public static function getChestContents() {
		$items = ['armor' => [[Item::LEATHER_CAP, Item::LEATHER_TUNIC, Item::LEATHER_PANTS, Item::LEATHER_BOOTS], [Item::GOLD_HELMET, Item::GOLD_CHESTPLATE, Item::GOLD_LEGGINGS, Item::GOLD_BOOTS], [Item::CHAIN_HELMET, Item::CHAIN_CHESTPLATE, Item::CHAIN_LEGGINGS, Item::CHAIN_BOOTS], [Item::IRON_HELMET, Item::IRON_CHESTPLATE, Item::IRON_LEGGINGS, Item::IRON_BOOTS], [Item::DIAMOND_HELMET, Item::DIAMOND_CHESTPLATE, Item::DIAMOND_LEGGINGS, Item::DIAMOND_BOOTS]], //WEAPONS
			'weapon' => [[Item::WOODEN_SWORD, Item::WOODEN_AXE,], [Item::GOLD_SWORD, Item::GOLD_AXE], [Item::STONE_SWORD, Item::STONE_AXE], [Item::IRON_SWORD, Item::IRON_AXE], [Item::DIAMOND_SWORD, Item::DIAMOND_AXE]], //FOOD
			'food' => [[Item::RAW_PORKCHOP, Item::RAW_CHICKEN, Item::MELON_SLICE, Item::COOKIE], [Item::RAW_BEEF, Item::CARROT], [Item::APPLE, Item::GOLDEN_APPLE], [Item::BEETROOT_SOUP, Item::BREAD, Item::BAKED_POTATO], [Item::MUSHROOM_STEW, Item::COOKED_CHICKEN], [Item::COOKED_PORKCHOP, Item::STEAK, Item::PUMPKIN_PIE],], //THROWABLE
			'throwable' => [[Item::BOW, Item::ARROW], [Item::SNOWBALL], [Item::EGG]], //BLOCKS
			'block' => [Item::STONE, Item::WOODEN_PLANKS, Item::COBBLESTONE, Item::DIRT], //OTHER
			'other' => [[Item::WOODEN_PICKAXE, Item::GOLD_PICKAXE, Item::STONE_PICKAXE, Item::IRON_PICKAXE, Item::DIAMOND_PICKAXE], [Item::STICK, Item::STRING]]];


		$templates = [];
		for ($i = 0; $i < 25; $i++) {

			$armorq = mt_rand(0, 1);
			$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
			$armor1 = [$armortype[\mt_rand(0, (\count($armortype) - 1))], 1];
			if ($armorq) {
				$armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
				$armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
			} else {
				$armor2 = array(0, 1);
			}
			unset($armorq, $armortype);

			$weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
			$weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
			unset($weapontype);

			$ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
			$food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
			unset($ftype);

			$add = mt_rand(0, 1);
			if ($add) {
				$tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
				if (count($tr) == 2) {
					$throwable1 = array($tr[1], mt_rand(10, 20));
					$throwable2 = array($tr[0], 1);
				} else {
					$throwable1 = array(0, 1);
					$throwable2 = array($tr[0], mt_rand(5, 10));
				}
				$other = array(0, 1);
			} else {
				$throwable1 = array(0, 1);
				$throwable2 = array(0, 1);
				$ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
				$other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
			}
			unset($add, $tr, $ot);

			$block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);

			$contents = array($armor1, $armor2, $weapon, $food, $throwable1, $throwable2, $block, $other);
			shuffle($contents);
			$fcontents = array(mt_rand(1, 2) => array_shift($contents), mt_rand(3, 5) => array_shift($contents), mt_rand(6, 10) => array_shift($contents), mt_rand(11, 15) => array_shift($contents), mt_rand(16, 17) => array_shift($contents), mt_rand(18, 20) => array_shift($contents), mt_rand(21, 25) => array_shift($contents), mt_rand(26, 27) => array_shift($contents),);
			$templates[] = $fcontents;
		}

		shuffle($templates);
		return $templates;
	}

}
