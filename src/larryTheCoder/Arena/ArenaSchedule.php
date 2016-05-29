<?php

namespace larryTheCoder\Arena;

use pocketmine\tile\Sign;
use pocketmine\scheduler\Task;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\UpdateAttributesPacket;

/**
 * ArenaSheduler : Scheduled game reseting
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < BETA | Testing >
 * 
 */
class ArenaSchedule extends Task {

    private $time = 0;
    private $startTime;
    private $updateTime = 0;
    private $forcestart = false;
    private $arena;

    #sign lines
    private $level;
    private $line1;
    private $line2;
    private $line3;
    private $line4;

    public function __construct(Arena $arena) {
        $this->arena = $arena;
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

    public function onRun($currentTick) {
        if (strtolower($this->arena->data['signs']['enable_status']) === 'true') {
            $this->updateTime++;
            if ($this->updateTime >= $this->arena->data['signs']['sign_update_time']) {
                $vars = ['%alive', '%dead', '%status', '%type', '%max', '&'];
                $replace = [count(array_merge($this->arena->ingamep, $this->arena->waitingp)), count($this->arena->deads), $this->arena->getStatus(), $this->arena->getMaxPlayers(), "§"];
                $tile = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world'])->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
                if ($tile instanceof Sign) {
                    $tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
                }
                $this->updateTime = 0;
            }
        }

        if ($this->arena->game === 0) {
            if (count($this->arena->waitingp) >= $this->arena->getMinPlayers() || $this->forcestart === true) {
                if ($this->startTime <= 0) {
                    $this->arena->startGame();
                    return;
                }
                $vars = ["%1", "%2", "%3"];
                $replace = [$this->startTime, count($this->arena->waitingp),$this->arena->getMaxPlayers()];
                $msg = str_replace($vars, $replace, $this->arena->plugin->getMsg('startime'));
                    foreach ($this->plugin->ingamep as $p) {
                        $p->sendTip($msg);
                    }
                $this->startTime--;
            } else {
                $this->startTime = $this->arena->data['arena']['starting_time'];
            }
        }
        if ($this->arena->game === 1) {
            $this->startTime = $this->arena->data['arena']['starting_time'];
            if (count($this->arena->ingamep <= 4)) {
                foreach ($this->arena->ingamep as $p) {
                    $this->arena->giveEffect(1, $p);
                }
            }
            if (count($this->arena->ingamep) <= 1) {
                $this->arena->stopGame();
            } else {
                if (count($this->arena->ingamep) <= 1) {
                    $this->arena->checkAlive();
                    $this->time++;
                }
            }
        }
    }

}
