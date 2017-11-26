<?php

# Checked with 0 Errors 2 Warnings

namespace larryTheCoder\arena;

use pocketmine\level\sound\ClickSound;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;

/**
 * Arena scheduler that will be used to run a game
 * This task will ticks the arena until ends
 *
 * @package larryTheCoder\arena
 */
final class ArenaSchedule extends Task {

	private $startTime = 60;
	private $mainTime;
	private $updateTime = 0;
	private $arena;

	#sign lines
	private $line1;
	private $line2;
	private $line3;
	private $line4;

	public function __construct(Arena $arena) {
		$this->arena = $arena;
		$this->mainTime = $this->arena->data['arena']['max_game_time'];
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

	public function onRun(int $currentTick) {
		/** @var Player $p */
		# Sign schedule for arena
		if (strtolower($this->arena->data['signs']['enable_status']) === 'true') {
			$this->updateTime++;
			if ($this->updateTime >= $this->arena->data['signs']['sign_update_time']) {
				$vars = ['%alive', '%dead', '%status', '%max', '&', '%world'];
				$replace = [count($this->arena->players), count($this->arena->dead), $this->arena->getStatus(), $this->arena->getMaxPlayers(), "§", $this->arena->data['arena']['arena_world']];
				$tile = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world'])->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
				if ($tile instanceof Sign) {
					$tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
				}
				$this->updateTime = 0;
			}
		}
		# Arena is not running
		if ($this->arena->game === 0) {
			if (count($this->arena->players) > $this->arena->getMinPlayers() - 1) {
				$this->startTime--;
				foreach ($this->arena->players as $p) {
					if ($p instanceof Player) {
						$p->sendPopup(str_replace("%1", $this->startTime, $this->arena->plugin->getMsg('starting')));
					}
					if ($this->startTime <= 10) {
						$p->getLevel()->addSound((new ClickSound($p)), [$p]);
					}
				}
				if ($this->startTime == 0) {
					$this->arena->startGame();
					$this->startTime = 60;
					$this->arena->forceStart = false;
					return;
				}
				if ($this->arena->plugin->cfg->get("start_when_full") && $this->arena->getMaxPlayers() - 1 < count($this->arena->players)) {
					$this->arena->startGame();
					$this->startTime = 60;
					$this->arena->forceStart = false;
					return;
				}
			} else {
				foreach ($this->arena->players as $p) {
					$p->sendPopup($this->arena->plugin->getMsg("wait"));
				}
				$this->startTime = 60;
			}
		}
		# Arena is running
		if ($this->arena->game === 1) {
			if ($this->arena->fallTime !== 0) {
				$this->arena->fallTime--;
			}

			if ($this->arena->data["chest"]["refill"] !== false && ($this->mainTime % $this->arena->data['chest']["refill_rate"]) == 0) {
				$this->arena->refillChests();
				$this->arena->messageArenaPlayers($this->arena->plugin->getMsg("chest_refilled"));
			}
			$this->mainTime--;
			if ($this->mainTime === 0) {
				$this->arena->stopGame();
				$this->arena->updateLevel = true;
			}
			$msg = str_replace("%1", date('i:src', $this->mainTime), $this->arena->plugin->getMsg('playing'));
			foreach ($this->arena->players as $p) {
				$p->sendPopup($msg);
			}
			// This will checks if there is 1 players left in arena
			// So there will no errors
			$this->arena->checkAlive();
		}
	}

}
