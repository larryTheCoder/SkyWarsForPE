<?php

namespace larryTheCoder\utils;

use Exception;
use larryTheCoder\SkyWarsPE;
use pocketmine\level\Level;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\utils\Random;
use pocketmine\utils\TextFormat;

/**
 * Portal helper for entering arenas for player
 *
 * @package larryTheCoder\utils
 */
class PortalManager {

	/**
	 *
	 * @param Player $player
	 * @param string $destinationLevelName
	 * @param Position $destinationPos
	 * @return boolean
	 */
	public static function doTeleporting(Player $player, $destinationLevelName, Position $destinationPos = null) {
		try {
			$player->onGround = true;
			if (($destinationPos != null && $destinationPos != false)) {
				if ($destinationLevelName != null && $destinationLevelName === $player->getLevel()->getName()) {
					$player->sendTip(TextFormat::GRAY . "Teleporting to same world");
					$player->teleport($destinationPos);
				} else {
					$player->sendTip(TextFormat::GRAY . "Teleporting to different world destination:" . $destinationLevelName);
					self::teleportWorldDestination($player, $destinationLevelName, $destinationPos);
				}
			} else {
				$player->sendTip(TextFormat::GRAY . "Teleport to different world :" . $destinationLevelName);
				self::teleportWorldDestination($player, $destinationLevelName, null);
			}
			$player->onGround = false;
			return true;
		} catch (Exception $e) {
			echo $e->getMessage() . "|" . $e->getLine() . " | " . $e->getTraceAsString();
		}
		return false;
	}

	/**
	 * teleporting
	 *
	 * @param Player $player
	 * @param string $levelName
	 * @param Position $pos
	 */
	final static public function teleportWorldDestination(Player $player, $levelName, Position $pos = null) {
		if (is_null($levelName) || empty($levelName)) {
			$player->sendMessage("unable teleport due missing destination level " . $levelName . "!");
			return;
		}

		if (!$player->getServer()->isLevelLoaded($levelName)) {
			$ret = $player->getServer()->loadLevel($levelName);
			if (!$ret) {
				$player->sendMessage(SkyWarsPE::getInstance()->getPrefix() . "Error on loading World: " . $levelName . ". please contact server administrator.");
				return;
			}
		}
		$level = $player->getServer()->getLevelByName($levelName);
		if (is_null($level)) {
			$player->sendMessage(SkyWarsPE::getInstance()->getPrefix() . "Unable find world: " . $levelName . ". please contact server administrator.");
			return;
		}

		// same world teleporting
		if ($pos instanceof Position) {
			//$level->loadChunk ( $level->getSafeSpawn ()->x, $level->getSafeSpawn ()->z );
			$player->teleport($level->getSafeSpawn());
			// position
			//$level->loadChunk ( $pos->x, $pos->z );
			$player->sendMessage(SkyWarsPE::getInstance()->getPrefix() . TextFormat::GRAY . "Teleporting [" . TextFormat::GOLD . $levelName . TextFormat::GRAY . "] at " . round($pos->x) . " " . round($pos->y) . " " . round($pos->z));
			$player->teleport(new Position($pos->x, $pos->y, $pos->z, $level));
			$level->updateAllLight($pos);
			$level->updateAround($pos);
			// }
		} elseif (is_null($pos) || empty($pos)) {
			//$level->loadChunk ( $level->getSafeSpawn ()->x, $level->getSafeSpawn ()->z );
			$player->sendMessage(TextFormat::GRAY . "[BH] TPW [" . TextFormat::GOLD . $levelName . TextFormat::GRAY . "]");
			$player->teleport($level->getSafeSpawn());
			$level->updateAllLight($pos);
			$level->updateAround($pos);
		}
	}

	final static function addParticles(Level $level, Position $pos1, $count = 5) {
		$xd = (float)280;
		$yd = (float)280;
		$zd = (float)280;

		$particle1 = new PortalParticle($pos1);
		$random = new Random((int)(microtime(true) * 1000) + mt_rand());
		for ($i = 0; $i < $count; ++$i) {
			$particle1->setComponents($pos1->x + $random->nextSignedFloat() * $xd, $pos1->y + $random->nextSignedFloat() * $yd, $pos1->z + $random->nextSignedFloat() * $zd);
			$level->addParticle($particle1);
		}
	}

}
