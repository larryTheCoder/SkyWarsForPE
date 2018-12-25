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
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\MoveEntityAbsolutePacket;
use pocketmine\Player;
use pocketmine\Server;

class FakeHuman extends Human {

	private $tickSkin = 0;
	private $levelPedestal;
	private $tags = [];

	public function __construct(Level $level, CompoundTag $nbt, int $pedestalLevel){
		parent::__construct($level, $nbt);

		$this->setCanSaveWithChunk(false);
		$this->setImmobile(false);
		$this->levelPedestal = $pedestalLevel;
	}

	public function onUpdate(int $currentTick): bool{
		// Look at the player, and sent the packet only
		// to the player who looked at it
		foreach($this->getLevel()->getPlayers() as $p){
			if($p->distance($this) <= 5){
				$this->lookAtInto($p);
			}
		}

		if($this->tickSkin >= 200){
			$db = SkyWarsPE::getInstance()->getDatabase()->getPlayers();
			// Avoid nulls and other consequences
			$player = []; // PlayerName => Kills
			$player["Example-1"] = 0;
			$player["Example-2"] = 0;
			$player["Example-3"] = 0;
			foreach($db as $value){
				$player[$value->player] = $value->wins;
			}

			arsort($player);
			$limit = 0;
			foreach($player as $p => $wins){
				$limit++;
				if($limit !== $this->levelPedestal){
					continue;
				}

				// Send the skin
				if(Server::getInstance()->getPlayer($p) === null
					&& file_exists(Server::getInstance()->getDataPath() . "players/" . strtolower($p) . ".dat")){
					$nbt = Server::getInstance()->getOfflinePlayerData($p);
					$skin = $nbt->getCompoundTag("Skin");
					if($skin !== \null){
						$this->skin = new Skin(
							$skin->getString("Name"),
							$skin->hasTag("Data", StringTag::class) ? $skin->getString("Data") : $skin->getByteArray("Data"), //old data (this used to be saved as a StringTag in older versions of PM)
							$skin->getByteArray("CapeData", ""),
							$skin->getString("GeometryName", ""),
							$skin->getByteArray("GeometryData", "")
						);
						$this->skin->debloatGeometryData();
						$this->sendSkin();
					}
				}else{
					$this->skin = Server::getInstance()->getPlayer($p)->getSkin();
					$this->sendSkin();
				}

				// The text packets
				$msg1 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $limit + 1, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-1', false));
				$msg2 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $limit + 1, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-2', false));
				$msg3 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $limit + 1, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-3', false));
				$array = [$msg1, $msg2, $msg3];
				$this->sendText($array);
			}
			$this->tickSkin = 0;
		}

		return true;
	}

	/**
	 * Changes the entity's yaw and pitch to make it look at the specified Vector3 position. For mobs, this will cause
	 * their heads to turn.
	 *
	 * @param Player $target
	 */
	private function lookAtInto(Player $target): void{
		$horizontal = sqrt(($target->x - $this->x) ** 2 + ($target->z - $this->z) ** 2);
		$vertical = ($target->y - $this->y) + 0.6; // 0.6 is the player offset.
		$this->pitch = -atan2($vertical, $horizontal) / M_PI * 180; //negative is up, positive is down

		$xDist = $target->x - $this->x;
		$zDist = $target->z - $this->z;
		$this->yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($this->yaw < 0){
			$this->yaw += 360.0;
		}
		$this->updateMovementInto($target);
	}

	private function updateMovementInto(Player $player){
		$pk = new MoveEntityAbsolutePacket();

		$pk->entityRuntimeId = $this->id;
		$pk->position = $this->asVector3();

		$pk->xRot = $this->pitch;
		$pk->yRot = $this->yaw; //TODO: head yaw
		$pk->zRot = $this->yaw;

		$player->sendDataPacket($pk);
	}

	private function sendText(array $text){
		$i = 1.85;
		$obj = 0;
		foreach($text as $value){
			if(isset($this->tags[$obj])){
				/** @var FloatingText $particle1 */
				$particle1 = $this->tags[$obj];
				$particle1->setTitle($value);
			}else{
				$particle1 = new FloatingTextParticle($this->add(0, $i), "", $value);
				$this->tags[$obj] = $particle1;
			}
			$this->level->addParticle($particle1);
			$i -= 0.3;
			$obj++;
		}
	}
}