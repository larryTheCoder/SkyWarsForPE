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
use larryTheCoder\utils\{
	Settings, Utils
};
use pocketmine\{
	entity\Effect, entity\EffectInstance, item\Item, nbt\tag\CompoundTag, Player
};
use pocketmine\block\WallSign;
use pocketmine\level\sound\ClickSound;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\tile\{
	Chest, Sign
};

/**
 * Arena scheduler that will be used to run a game
 * This task will ticks the arena until ends
 *
 * @package larryTheCoder\arena
 */
class ArenaSchedule extends Task {

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
	private $mainTime = 0;
	private $endTime = 0;
	private $updateTime = 0;
	private $arena;

	private $chestTick = false;
	private $tickTipBar = 0;

	public function __construct(Arena $arena){
		$this->arena = $arena;
		$this->mainTime = $this->arena->data['arena']['max_game_time'];
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
				$tile = $this->arena->plugin->getServer()->getLevelByName($this->arena->data['signs']['join_sign_world'])->getTile(new Vector3($this->arena->data['signs']['join_sign_x'], $this->arena->data['signs']['join_sign_y'], $this->arena->data['signs']['join_sign_z']));
				if($tile instanceof Sign){
					$block = $tile->getLevel()->getBlock($tile);
					if($block instanceof WallSign){
						$vec = $block->getSide($block->getDamage() ^ 0x01);
						$tile->getLevel()->setBlock($vec, $this->arena->getBlockStatus());
					}
					$tile->setText(str_replace($vars, $replace, $this->line1), str_replace($vars, $replace, $this->line2), str_replace($vars, $replace, $this->line3), str_replace($vars, $replace, $this->line4));
				}
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
					$this->mainTime = $this->arena->data['arena']['max_game_time'];
					$this->endTime = 0;
				}
				break;
			case Arena::ARENA_RUNNING:
				$this->arena->totalPlayed++;
				if($this->arena->fallTime !== 0){
					$this->arena->fallTime--;
				}

				$this->tickEffect();

				$refill = ($this->mainTime % $this->arena->data['chest']["refill_rate"]);
				if($this->arena->data["chest"]["refill"] !== false && $refill == 0){
					$this->arena->refillChests();
					$this->arena->messageArenaPlayers("chest-refilled", false);
					foreach($this->arena->getArenaLevel()->getTiles() as $tiles){
						if($tiles instanceof Chest){
							$task = new ParticleTask($tiles);
							$this->arena->plugin->getScheduler()->scheduleRepeatingTask($task, 1);
						}
					}
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


				$this->mainTime--;
				if($this->mainTime === 0){
					$this->arena->totalPlayed = 0;
					$this->arena->setGame(Arena::ARENA_CELEBRATING);
					foreach($this->arena->players as $player){
						$player->setXpLevel(0);
						$player->removeAllEffects();
						$player->getInventory()->clearAll();
						$player->getArmorInventory()->clearAll();
						$player->setGamemode(Player::CREATIVE);
						$player->setInvisible();
						//$this->arena->giveGameItems($player, true);
						$toGive = Item::get(358, 0);
						$tag = new CompoundTag();
						$tag->setTag(new CompoundTag("", []));
						$tag->setString("map_uuid", 18293883);
						$toGive->setCompoundTag($tag);
						$p->getInventory()->setItem(8, $toGive, true);
					}

					return;
				}

				$space = str_repeat(" ", 78); // 55 default
				if($refill !== $this->mainTime + 1){
					$timer = gmdate('i:s', $refill);
				}else{
					$timer = 0;
				}
				foreach($this->arena->players as $p){
					$timerPlace = "";
					if($timer !== 0){
						$timerPlace = $space . "§bNext refill: $timer\n";
					}
					$p->sendTip("\n" .
						$space . "§0[ §eSkyWars §0]\n" . $timerPlace .
						$space . "§bNick: " . $p->getName() . "\n" .
						$space . "§bPlayers: §c" . count($this->arena->players) . "/" . $this->arena->getMaxPlayers() . "\n" .
						$space . "§eKills: " . $this->arena->kills[strtolower($p->getName())] . "\n" .
						$space . "§eTime: " . gmdate('i:s', $this->mainTime) . "\n" .
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
				break;
			case Arena::ARENA_CELEBRATING:
				if($this->endTime === 0){
					$this->arena->broadcastResult();
				}
				$this->endTime++;

				if(empty($this->arena->players)){
					$this->arena->stopGame(true);
					$this->endTime = 0;

					return;
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
			switch($this->mainTime){
				case ($this->mainTime / 2):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::NAUSEA), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 10 * 60, 1));
					break;
				case ($this->mainTime / 3):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::JUMP), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::POISON), 10 * 60, 1));
					break;
				case ($this->mainTime / 4):
					$player->sendMessage($this->arena->plugin->getMsg($player, "messageEffectIsComing", true));

					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::DAMAGE_RESISTANCE), 20 * 60, 1));
					$player->addEffect(new EffectInstance(Effect::getEffect(Effect::REGENERATION), 10 * 60, 1));
					break;
			}
		}
	}

}
