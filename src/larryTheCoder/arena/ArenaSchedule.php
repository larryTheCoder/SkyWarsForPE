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

namespace larryTheCoder\arena;

use larryTheCoder\SkyWarsPE;
use larryTheCoder\task\ParticleTask;
use larryTheCoder\utils\{Settings, Utils};
use pocketmine\{entity\Effect,
	entity\EffectInstance,
	network\mcpe\protocol\BlockEventPacket,
	network\mcpe\protocol\LevelSoundEventPacket,
	Player};
use pocketmine\block\WallSign;
use pocketmine\level\sound\ClickSound;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\tile\{Chest, Sign};

/**
 * Arena scheduler that will be used to run a game
 * This task will ticks the arena until ends
 *
 * @package larryTheCoder\arena
 */
class ArenaSchedule extends Task {

	// Scoreboard constants
	const WAITING_SC = 0;
	const STARTING_SC = 1;

	/** @var string */
	public $line1;
	/** @var string */
	public $line2;
	/** @var string */
	public $line3;
	/** @var string */
	public $line4;
	private $startTime = 60;

	# sign lines
	private $endTime = 0;
	private $updateTime = 0;
	private $arena;

	private $chestTick = 0;
	private $tickTipBar = 0;

	public function __construct(Arena $arena){
		$this->arena = $arena;
		$this->line1 = str_replace("&", "§", $this->arena->data['signs']['status_line_1']);
		$this->line2 = str_replace("&", "§", $this->arena->data['signs']['status_line_2']);
		$this->line3 = str_replace("&", "§", $this->arena->data['signs']['status_line_3']);
		$this->line4 = str_replace("&", "§", $this->arena->data['signs']['status_line_4']);
		if(!$this->arena->plugin->getServer()->isLevelGenerated($this->arena->data['signs']['join_sign_world'])){
			$this->arena->plugin->getServer()->generateLevel($this->arena->data['signs']['join_sign_world']);
			$this->arena->plugin->getServer()->loadLevel($this->arena->data['signs']['join_sign_world']);
		}
		if(!$this->arena->plugin->getServer()->isLevelLoaded($this->arena->data['signs']['join_sign_world'])){
			$this->arena->plugin->getServer()->loadLevel($this->arena->data['signs']['join_sign_world']);
		}
	}

	public function onRun(int $currentTick){
		/** @var Player $p */
		# Sign schedule for arena
		if($this->arena->data['signs']['enable_status'] === true){
			$this->updateTime++;
			if($this->updateTime >= $this->arena->data['signs']['sign_update_time']){
				$vars = ['%alive', '%status', '%max', '&', '%world', '%prefix', '%name'];
				$replace = [count($this->arena->players), $this->arena->getStatus(), $this->arena->getMaxPlayers(), "§", $this->arena->data['arena']['arena_world'], SkyWarsPE::getInstance()->getPrefix(), $this->arena->data['arena-name']];
				$level = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world']);
				if($level === null){
					goto skipUpdate;
				}
				$tile = $level->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
				if($tile instanceof Sign){
					$block = $tile->getLevel()->getBlock($tile);
					if($block instanceof WallSign){
						$vec = $block->getSide($block->getDamage() ^ 0x01);
						$tile->getLevel()->setBlock($vec, $this->arena->getBlockStatus());
					}
					$tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
				}
				skipUpdate:
				$this->updateTime = 0;
			}
		}
		# Update current status
		$this->arena->statusUpdate();
		$this->arena->checkLevelTime();
		# Arena is not running
		switch($this->arena->getMode()){
			case Arena::ARENA_WAITING_PLAYERS:
				$this->arena->totalPlayed = 0;
				if(!empty($this->arena->players) && count($this->arena->players) > $this->arena->getMinPlayers() - 1){
					$this->startTime--;
					foreach($this->arena->players as $p){
						if($p instanceof Player){
							$p->setXpLevel($this->startTime);
						}
						if($this->startTime <= 11){
							$p->getLevel()->addSound((new ClickSound($p)), [$p]);
							if($this->startTime === 11){
								$p->addTitle($this->arena->plugin->getMsg($p, 'arena-starting', false));
							}elseif($this->startTime <= 3){
								$p->addSubTitle($this->arena->plugin->getMsg($p, 'arena-subtitle', false));
								if($this->startTime > 1){
									$p->addTitle("§6" . $this->startTime);
								}else{
									$p->addTitle("§c" . $this->startTime);
								}
							}else{
								$p->addTitle("§a" . $this->startTime);
							}
						}
					}

					if($this->startTime == 0){
						$this->arena->startGame();
						$this->startTime = 60;
						break;
					}
					if(Settings::$startWhenFull && $this->arena->getMaxPlayers() - 1 < count($this->arena->players)){
						$this->arena->startGame();
						$this->startTime = 60;
						break;
					}
				}else{
					foreach($this->arena->players as $p){
						if($this->startTime < 60){
							$p->sendPopup($this->arena->plugin->getMsg($p, "arena-low-players", false));
						}else{
							$p->sendPopup($this->arena->plugin->getMsg($p, "arena-wait-players", false));
						}
					}
					$this->startTime = 60;
					$this->chestTick = 0;
					$this->endTime = 0;
				}
				break;
			case Arena::ARENA_RUNNING:
				$this->arena->totalPlayed++;
				if($this->arena->fallTime !== 0){
					$this->arena->fallTime--;
				}

				$this->tickEffect();

				$refill = ($this->chestTick % $this->arena->data['chest']["refill_rate"]);
				if($this->arena->data["chest"]["refill"] !== false && $refill == 0){
					$this->tickChest();
					$this->arena->refillChests();
					$this->arena->messageArenaPlayers("chest-refilled", false);
					foreach($this->arena->getArenaLevel()->getTiles() as $tiles){
						if($tiles instanceof Chest){
							$task = new ParticleTask($tiles);
							$this->arena->plugin->getScheduler()->scheduleRepeatingTask($task, 1);
						}
					}
					$this->chestTick = 0;
				}
				if($this->tickTipBar === 15){
					foreach($this->arena->players as $player){
						$player->sendPopup("§c1. §a{$this->arena->winners[0][0]} -> {$this->arena->winners[0][1]} kills" .
							"\n§c2. §a{$this->arena->winners[1][0]} -> {$this->arena->winners[1][1]} kills" .
							"\n§c3. §a{$this->arena->winners[2][0]} -> {$this->arena->winners[2][1]} kills");
					}
					$this->tickTipBar = 0;
				}
				$this->tickTipBar++;

				$this->chestTick++;

				foreach($this->arena->players as $p){
					$this->sendScoreboard($p);
				}
				break;
			case Arena::ARENA_CELEBRATING:
				if($this->endTime === 0){
					$this->arena->broadcastResult();
				}
				$this->endTime++;

				if(empty($this->arena->players)){
					$this->arena->stopGame(true);
					$this->endTime = 0;

					break;
				}

				foreach($this->arena->players as $player){
					$facing = $player->getDirection();
					$vec = $player->getSide($facing, -3);
					Utils::addFireworks($vec);
					$player->sendPopup("§c1. §a{$this->arena->winners[0][0]} -> {$this->arena->winners[0][1]} kills" .
						"\n§c2. §a{$this->arena->winners[1][0]} -> {$this->arena->winners[1][1]} kills" .
						"\n§c3. §a{$this->arena->winners[2][0]} -> {$this->arena->winners[2][1]} kills");

				}

				if($this->endTime >= 11){
					$this->arena->stopGame(true);
					$this->endTime = 0;
				}
				$this->tickTipBar = 0;
				$this->chestTick = 0;
		}

		// This will checks if there is 1 players left in arena
		// So there will no errors
		$this->arena->checkAlive();
	}

	/**
	 * Ticks some cool effect to the players
	 * They will feel dizzy and weird at the
	 * same time.
	 */
	private function tickEffect(){
		if(Settings::$isModded){
			return;
		}
		foreach($this->arena->players as $player){
			switch($this->chestTick){
				case ($this->chestTick / 2):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::NAUSEA), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 10 * 60, 1));
					break;
				case ($this->chestTick / 3):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::JUMP), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::POISON), 10 * 60, 1));
					break;
				case ($this->chestTick / 4):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::DAMAGE_RESISTANCE), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 10 * 60, 1));
					break;
			}
		}
	}

	public function sendScoreboard(Player $p){
		$useLegacyMethod = true;
		if(!$useLegacyMethod){
			$refill = ($this->chestTick % $this->arena->data['chest']["refill_rate"]);

			if($refill !== $this->chestTick + 1){
				$timer = gmdate('i:s', $refill);
			}else{
				$timer = 0;
			}
			$score = $this->arena->getScoreboard();

			$i = 0;
			$score->setLine($p, $i++, "[SkyWars]");
			$score->setLine($p, $i++, "§bNext refill: $timer");
			$score->setLine($p, $i++, "§bPlayers: §c" . count($this->arena->players) . "/" . $this->arena->getMaxPlayers() . "\n");
			$score->setLine($p, $i++, "§eKills: " . $this->arena->kills[strtolower($p->getName())] . "\n");
			$score->setLine($p, $i, "§eTime: " . gmdate('i:s', $this->chestTick) . "\n");
		}else{
			$refill = ($this->chestTick % $this->arena->data['chest']["refill_rate"]);

			$space = str_repeat(" ", 78); // 55 default
			if($refill !== $this->chestTick + 1){
				$timer = gmdate('i:s', $refill);
			}else{
				$timer = 0;
			}
			$timerPlace = "";
			if($timer !== 0){
				$timerPlace = $space . "§bNext refill: $timer\n";
			}

			$p->sendTip("\n" .
				$space . "§0[ §eSkyWars §0]\n" . $timerPlace .
				$space . "§bNick: " . $p->getName() . "\n" .
				$space . "§bPlayers: §c" . count($this->arena->players) . "/" . $this->arena->getMaxPlayers() . "\n" .
				$space . "§eKills: " . $this->arena->kills[strtolower($p->getName())] . "\n" .
				$space . "§eTime: " . gmdate('i:s', $this->chestTick) . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space . "\n" .
				$space);
		}
	}

	/**
	 * Close the chest and send them to all of the players
	 * in the arena.
	 */
	private function tickChest(){
		$level = $this->arena->getArenaLevel();
		foreach($this->arena->chestId as $chest){
			/** @var Vector3 $chestPos */
			$chestPos = $chest[0];

			$pk = new BlockEventPacket();
			$pk->x = (int)$chestPos->x;
			$pk->y = (int)$chestPos->y;
			$pk->z = (int)$chestPos->z;
			$pk->eventType = 1; // It's always 1 for a chest
			$pk->eventData = 0; // Close the chest lmao
			$level->broadcastPacketToViewers($chestPos, $pk);
		}
		// Send sound to all of the players
		$level->broadcastLevelSoundEvent(new Vector3(), LevelSoundEventPacket::SOUND_CHEST_CLOSED, -1, -1, false, true);
	}
}
