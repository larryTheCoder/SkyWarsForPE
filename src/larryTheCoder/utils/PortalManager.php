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

use Exception;
use larryTheCoder\SkyWarsPE;
use pocketmine\level\{
    Level, particle\PortalParticle, Position
};
use pocketmine\Player;
use pocketmine\utils\{
    Random, TextFormat
};

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
     * @return bool
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
            // }
        } elseif (is_null($pos) || empty($pos)) {
            //$level->loadChunk ( $level->getSafeSpawn ()->x, $level->getSafeSpawn ()->z );
            $player->sendMessage(TextFormat::GRAY . "[BH] TPW [" . TextFormat::GOLD . $levelName . TextFormat::GRAY . "]");
            $player->teleport($level->getSafeSpawn());
            $level->updateAllLight($pos);
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
