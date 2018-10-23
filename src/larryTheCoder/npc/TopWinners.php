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

namespace larryTheCoder\npc;

use larryTheCoder\SkyWarsPE;
use pocketmine\{
	event\entity\EntityLevelChangeEvent, event\player\PlayerJoinEvent, Player, Server
};
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\level\{
	Level, Position
};
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

/**
 * Task in LISTENER.
 *
 * Class TopWinners
 * @package larryTheCoder\npc
 */
class TopWinners extends Task implements Listener {

	/** @var NPC */
	public $npc1 = null;
	/** @var NPC */
	public $npc2 = null;
	/** @var NPC */
	public $npc3 = null;
	/** @var SkyWarsPE */
	private $plugin;
	/** @var bool */
	private $close = false;
	/** @var int */
	private $tickNPCSkin = 300;
	/** @var FloatingText[] */
	private $tags = [];
	/** @var Config */
	private $cfg;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;
		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		$this->cfg = new Config($this->plugin->getDataFolder() . "npc.yml", Config::YAML);
	}

	/**
	 * Tick the NPC.
	 *
	 * @param int $currentTick
	 * @return void
	 */
	public function onRun(int $currentTick){
		if($this->close){
			return;
		}
		$npc1E = $this->cfg->get("npc-1", []);
		$npc2E = $this->cfg->get("npc-2", []);
		$npc3E = $this->cfg->get("npc-3", []);
		if(count($npc1E) < 1 || count($npc2E) < 1 || count($npc3E) < 1){
			return;
		}

		$npc1 = new Position($npc1E[0], $npc1E[1], $npc1E[2], Server::getInstance()->getLevelByName($npc1E[3]));
		$npc2 = new Position($npc2E[0], $npc2E[1], $npc2E[2], Server::getInstance()->getLevelByName($npc2E[3]));
		$npc3 = new Position($npc3E[0], $npc3E[1], $npc3E[2], Server::getInstance()->getLevelByName($npc3E[3]));

		if(is_null($this->npc1)){
			$entity = new NPC($npc1, $npc1->getLevel());
			$this->npc1 = $entity;
			$npc1->getLevel()->addParticle($this->npc1);
		}
		if(is_null($this->npc2)){
			$entity = new NPC($npc2, $npc1->getLevel());
			$this->npc2 = $entity;
			$npc2->getLevel()->addParticle($this->npc2);
		}
		if(is_null($this->npc3)){
			$entity = new NPC($npc3, $npc1->getLevel());
			$this->npc3 = $entity;
			$npc3->getLevel()->addParticle($this->npc3);
		}

		$i = 0;
		if($this->tickNPCSkin >= 200){
			$db = $this->plugin->getDatabase()->getPlayers();
			// Avoid nulls and other consequences
			$player = []; // PlayerName => Kills
			$player["Example-1"] = 0;
			$player["Example-2"] = 0;
			$player["Example-3"] = 0;
			foreach($db as $value){
				$player[$value->player] = $value->wins;
			}

			arsort($player);
			foreach($player as $p => $wins){
				if($i >= 3){
					break;
				}

				if(Server::getInstance()->getPlayer($p) === null){
					if(file_exists(Server::getInstance()->getDataPath() . "players/" . strtolower($p) . ".dat")){
						$nbt = Server::getInstance()->getOfflinePlayerData($p);
						$skinData = $nbt->getCompoundTag("Skin");
						$skin = new Skin(
							'Standard_Custom',
							$skinData->getByteArray("Data"),
							$skinData->getByteArray("CapeData"),
							$skinData->getString("GeometryName"),
							$skinData->getByteArray("GeometryData"));
					}else{
						$skin = null;
					}
				}else{
					$skin = Server::getInstance()->getPlayer($p)->getSkin();
				}

				$msg1 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $i + 1, $wins], $this->plugin->getMsg(null, 'top-winner-1', false));
				$msg2 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $i + 1, $wins], $this->plugin->getMsg(null, 'top-winner-2', false));
				$msg3 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $i + 1, $wins], $this->plugin->getMsg(null, 'top-winner-3', false));
				$msgS = [$msg1, $msg2, $msg3];

				switch($i){
					case 0:
						$this->sendText(0, $this->npc1, $msgS, $npc1->getLevel());
						$this->npc1->setSkin($skin);
						break;
					case 1:
						$this->sendText(1, $this->npc2, $msgS, $npc2->getLevel());
						$this->npc2->setSkin($skin);
						break;
					case 2:
						$this->sendText(2, $this->npc3, $msgS, $npc3->getLevel());
						$this->npc3->setSkin($skin);
						break;
				}
				$i++;
			}
			$this->tickNPCSkin = 0;
		}
		$this->tickNPCSkin++;

		foreach(Server::getInstance()->getOnlinePlayers() as $p){
			if($p->distance($this->npc1) <= 5){
				$this->npc1->lookAt($p);
			}
			if($p->distance($this->npc2) <= 5){
				$this->npc2->lookAt($p);
			}
			if($p->distance($this->npc3) <= 5){
				$this->npc3->lookAt($p);
			}
		}
	}

	private function sendText(int $id, Vector3 $vec, array $text, Level $level){
		$i = 1.85;
		$obj = 0;
		foreach($text as $value){
			if(isset($this->tags[$id][$obj])){
				/** @var FloatingText $particle1 */
				$particle1 = $this->tags[$id][$obj];
				$particle1->setTitle($value);
			}else{
				$particle1 = new FloatingText($vec->add(0, $i), "", $value);
				$this->tags[$id][$obj] = $particle1;
			}
			$level->addParticle($particle1);
			$i -= 0.3;
			$obj++;
		}
	}

	public function cleanUp(){
		foreach($this->tags as $particle){
			$particle->remove();
		}
		if(is_null($this->npc1) || is_null($this->npc2) || is_null($this->npc3)){
			return;
		}
		$this->npc1->remove();
		$this->npc2->remove();
		$this->npc3->remove();
		$this->npc1 = null;
		$this->npc2 = null;
		$this->npc3 = null;
	}

	/**
	 * @priority LOWEST
	 * @param EntityLevelChangeEvent $event
	 */
	public function onLevelChange(EntityLevelChangeEvent $event){
		$entity = $event->getEntity();
		if(is_null($this->npc1) || is_null($this->npc2) || is_null($this->npc3)){
			return;
		}
		if($entity instanceof Player){
			if($this->npc1->inLevel($entity)){
				$this->npc1->showToPlayer($entity);
			}
			if($this->npc2->inLevel($entity)){
				$this->npc2->showToPlayer($entity);
			}
			if($this->npc3->inLevel($entity)){
				$this->npc3->showToPlayer($entity);
			}
		}
	}

	/**
	 * @priority LOWEST
	 * @param PlayerJoinEvent $event
	 */
	public function onPlayerJoin(PlayerJoinEvent $event){
		$p = $event->getPlayer();
		if(is_null($this->npc1) || is_null($this->npc2) || is_null($this->npc3)){
			return;
		}
		if($this->npc1->inLevel($p)){
			$this->npc1->showToPlayer($p);
		}
		if($this->npc2->inLevel($p)){
			$this->npc2->showToPlayer($p);
		}
		if($this->npc3->inLevel($p)){
			$this->npc3->showToPlayer($p);
		}
	}
}