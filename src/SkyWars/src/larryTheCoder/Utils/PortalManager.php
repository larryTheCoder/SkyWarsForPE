<?php

namespace larryTheCoder\Utils;

use pocketmine\math\Vector3 as Vector3;
use pocketmine\level\Position;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;
use larryTheCoder\SkyWarsAPI;
use pocketmine\level\particle\PortalParticle;
use pocketmine\utils\Random;
use pocketmine\level\particle\ExplodeParticle;

/**
 * Arena:Portal Manager:Arena join event helper
 *
 * @author Windows 7
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
                    $player->teleportImmediate($destinationPos);
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
        } catch (\Exception $e) {
            echo $e->getMessage() . "|" . $e->getLine() . " | " . $e->getTraceAsString();
        }
        return false;
    }

    /**
     * teleporting
     *
     * @param Player $player        	
     * @param string $levelname        	
     * @param Position $pos        	
     */
    final static public function teleportWorldDestination(Player $player, $levelname, Position $pos = null) {
        if (is_null($levelname) || empty($levelname)) {
            $player->sendMessage("unable teleport due missing destination level " . $levelname . "!");
            return;
        }

        if (!$player->getServer()->isLevelLoaded($levelname)) {
            $ret = $player->getServer()->loadLevel($levelname);
            if (!$ret) {
                $player->sendMessage(SkyWarsAPI::getInstance()->getPrefix()."Error on loading World: " . $levelname . ". please contact server administrator.");
                return;
            }
        }
        $level = $player->getServer()->getLevelByName($levelname);
        if (is_null($level)) {
            $player->sendMessage(SkyWarsAPI::getInstance()->getPrefix()."Unable find world: " . $levelname . ". please contact server administrator.");
            return;
        }

        // same world teleporting
        if ($pos instanceof Position) {
            //$level->loadChunk ( $level->getSafeSpawn ()->x, $level->getSafeSpawn ()->z );
            $player->teleport($level->getSafeSpawn());
            // position
            //$level->loadChunk ( $pos->x, $pos->z );
            $player->sendMessage(kyWarsAPI::getInstance()->getPrefix().TextFormat::GRAY . "Teleporting [" . TextFormat::GOLD . $levelname . TextFormat::GRAY . "] at " . round($pos->x) . " " . round($pos->y) . " " . round($pos->z));
            $player->teleport(new Position($pos->x, $pos->y, $pos->z, $level));
            $level->updateAllLight($pos);
            $level->updateAround($pos);
            // }
        } elseif (is_null($pos) || empty($pos)) {
            //$level->loadChunk ( $level->getSafeSpawn ()->x, $level->getSafeSpawn ()->z );
            $player->sendMessage(TextFormat::GRAY . "[BH] TPW [" . TextFormat::GOLD . $levelname . TextFormat::GRAY . "]");
            $player->teleport($level->getSafeSpawn());
            $level->updateAllLight($pos);
            $level->updateAround($pos);
        }
    }

    final static function addParticles(Level $level, Position $pos1, $count = 5) {
        $xd = (float) 280;
        $yd = (float) 280;
        $zd = (float) 280;

        $particle1 = new PortalParticle($pos1);
        $random = new Random((int) (\microtime(\true) * 1000) + \mt_rand());
        for ($i = 0; $i < $count; ++$i) {
            $particle1->setComponents($pos1->x + $random->nextSignedFloat() * $xd, $pos1->y + $random->nextSignedFloat() * $yd, $pos1->z + $random->nextSignedFloat() * $zd);
            $level->addParticle($particle1);
        }
    }

}
