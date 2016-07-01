<?php

// LANGUAGE CHECK SUCCESS

namespace larryTheCoder\Arena;

use pocketmine\tile\Sign;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\level\sound\ButtonClickSound;

/**
 * ArenaSheduler : Scheduled game reseting
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < Alpha >
 * 
 */
final class ArenaSchedule extends Task {

    private $startTime;
    private $mainTime;
    private $updateTime = 0;
    private $arena;
    private $time = 0;

    #sign lines
    private $line1;
    private $line2;
    private $line3;
    private $line4;

    public function __construct(Arena $arena) {
        $this->arena = $arena;
        $this->mainTime = $this->arena->data['arena']['max_game_time'];
        $this->startTime = $this->arena->data['arena']['starting_time'];
        $this->line1 = str_replace("&", "§", $this->arena->data['signs']['status_line_1']);
        $this->line2 = str_replace("&", "§", $this->arena->data['signs']['status_line_2']);
        $this->line3 = str_replace("&", "§", $this->arena->data['signs']['status_line_3']);
        $this->line4 = str_replace("&", "§", $this->arena->data['signs']['status_line_4']);
        if (!$this->arena->plugin->getServer()->isLevelGenerated($this->arena->data['signs']['join_sign_world'])) {
            $this->arena->plugin->getServer()->generateLevel($this->arena->data['signs']['join_sign_world']);
            $this->arena->plugin->getServer()->loadLevel($this->arena->data['signs']['join_sign_world']);
        }
        if (!$this->arena->plugin->getServer()->isLevelLoaded($this->arena->data['signs']['join_sign_world'])) {
            $this->arena->plugin->getServer()->loadLevel($this->arena->data['signs']['join_sign_world']);
        }
    }

    // TO-DO 
    public function onRun($currentTick) {
        if (strtolower($this->arena->data['signs']['enable_status']) === 'true') {
            $this->updateTime++;
            if ($this->updateTime >= $this->arena->data['signs']['sign_update_time']) {
                $vars = ['%alive', '%dead', '%status', '%max', '&', '%world'];
                $replace = [count($this->arena->players), count($this->arena->deads), $this->arena->getStatus(), $this->arena->getMaxPlayers(), "§", $this->arena->data['arena']['arena_world']];
                $tile = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world'])->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
                if ($tile instanceof Sign) {
                    $tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
                }
                $this->updateTime = 0;
            }
        }
        $this->time++;
        // Arena condition: IN_CAGE
        if ($this->arena->game === 0) {
            if (count($this->arena->players) >= $this->arena->getMinPlayers() || $this->arena->forcestart === true) {
                $this->startTime--;
                foreach ($this->arena->players as $p) {
                    $p->sendPopup(str_replace("%1", date('i:s',$this->startTime), $this->arena->plugin->getMsg('starting')));
                }
                if ($this->startTime <= 0) {
                    if (count($this->arena->players) >= $this->arena->getMinPlayers() || $this->arena->forcestart === true) {
                        $this->arena->startGame();
                        $this->startTime = $this->arena->data['arena']['starting_time'];
                        $this->arena->forcestart = false;
                    } else {
                        $this->startTime = $this->arena->data['arena']['starting_time'];
                    }
                }
            } else {
                $this->startTime = $this->arena->data['arena']['starting_time'];
            }
        }
        // Arena condition: IN_GAME
        if ($this->arena->game === 1) {
            $this->startTime = $this->arena->data['arena']['starting_time'];
            $this->mainTime--;
            if ($this->mainTime === 0) {
                $this->arena->stopGame();
                $this->arena->plugin->getServer()->getLogger()->info($this->arena->plugin->getPrefix() . TextFormat::RED . "Arena level " . TextFormat::GREEN . $this->arena->data['arena']['arena_world'] . TextFormat::RED . " has stopeed!");
            }
            foreach ($this->arena->players as $p) {
                $p->sendPopup($msg);
            }
        }
    }

}
