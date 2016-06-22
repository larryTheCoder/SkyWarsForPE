<?php

namespace larryTheCoder\Arena;

use pocketmine\tile\Sign;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\Task;
use pocketmine\math\Vector3;

/**
 * ArenaSheduler : Scheduled game reseting
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < Alpha >
 * 
 */
class ArenaSchedule extends Task {

    private $time = 0;
    private $startTime;
    private $mainTime;
    private $updateTime = 0;
    private $arena;

    #sign lines
    private $level;
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

    public function onRun($currentTick) {
        if (strtolower($this->arena->data['signs']['enable_status']) === 'true') {
            $this->updateTime++;
            if ($this->updateTime >= $this->arena->data['signs']['sign_update_time']) {
                $vars = ['%alive', '%dead', '%status', '%max', '&', '%world'];
                $replace = [count(array_merge($this->arena->ingamep, $this->arena->waitingp)), count($this->arena->deads), $this->arena->getStatus(), $this->arena->getMaxPlayers(), "§", $this->arena->data['arena']['arena_world']];
                $tile = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world'])->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
                if ($tile instanceof Sign) {
                    $tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
                }
                $this->updateTime = 0;
            }
        }
        // on cage
        if ($this->arena->game === 0) {
            if (count($this->arena->waitingp) >= $this->arena->getMinPlayers() || $this->arena->forcestart === true) {
                $this->startTime--;
                $vars = ["%1", "%2", "%3"];
                $replace = [$this->startTime, count($this->arena->waitingp), $this->arena->getMaxPlayers()];
                $msg = str_replace($vars, $replace, $this->arena->plugin->getMsg('start_time'));
                foreach ($this->arena->waitingp as $p) {
                    $p->sendPopup($msg);
                }
                if ($this->startTime <= 0) {
                    $this->arena->startGame();
                    $this->arena->plugin->getServer()->getLogger()->info($this->arena->plugin->getPrefix() . TextFormat::GREEN . "Arena level " . TextFormat::RED . $this->arena->data['arena']['arena_world'] . TextFormat::GREEN . " has started!");
                    $this->arena->forcestart = false;
                    return;
                } else {
                    $this->startTime = $this->arena->data['arena']['starting_time'];
                }
            } else {
                $this->startTime = $this->arena->data['arena']['starting_time'];
            }
        }
        // game started
        if ($this->arena->game === 1) {
            $this->startTime = $this->arena->data['arena']['starting_time'];
            $this->mainTime--;
            if ($this->mainTime === 0) {
                $this->arena->stopGame();
                $this->arena->plugin->getServer()->getLogger()->info($this->arena->plugin->getPrefix() . TextFormat::RED . "Arena level " . TextFormat::GREEN . $this->arena->data['arena']['arena_world'] . TextFormat::RED . " has stopeed!");
            }
            foreach ($this->arena->waitingp as $p) {
                $p->sendPopup($msg);
            }
        }
    }

}
