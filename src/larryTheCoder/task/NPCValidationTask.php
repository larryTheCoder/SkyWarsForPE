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

namespace larryTheCoder\task;

use larryTheCoder\npc\FakeHuman;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class NPCValidationTask extends Task {

	/** @var boolean */
	private static $changed;
	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;
		self::$changed = false;
	}

	public static function setChanged(){
		self::$changed = true;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		$entity = $this->plugin->entities;
		foreach($entity as $key => $value){
			if($value !== null && $value->isValid()){
				continue;
			}

			// Respawn them
			$this->respawn($key);
		}

		if(self::$changed){
			unset($this->plugin->entities);
			foreach($entity as $key => $value){
				$value->close();
			}

			$this->respawn(0);
			$this->respawn(1);
			$this->respawn(2);

			self::$changed = false;
		}
	}

	private function respawn(int $key){
		$cfg = new Config($this->plugin->getDataFolder() . "npc.yml", Config::YAML);
		$npc = $cfg->get("npc-" . ($key + 1), []);

		if(count($npc) < 1){
			$this->plugin->getServer()->getLogger()->info($this->plugin->getPrefix() . "§7No TopWinners spawn location were found.");
			$this->plugin->getServer()->getLogger()->info($this->plugin->getPrefix() . "§7Please reconfigure TopWinners spawn locations");

			return;
		}

		Utils::loadFirst($npc[3]);
		$level = $this->plugin->getServer()->getLevelByName($npc[3]);

		$entity = new FakeHuman($level, Entity::createBaseNBT(new Vector3($npc[0], $npc[1], $npc[2])), ($key + 1));
		$entity->spawnToAll();

		$this->plugin->entities[$key] = $entity;
	}
}