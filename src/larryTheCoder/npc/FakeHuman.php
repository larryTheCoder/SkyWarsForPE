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
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\MoveEntityAbsolutePacket;
use pocketmine\Player;

class FakeHuman extends Human {

	// Override methods
	protected $gravity = 0;
	protected $drag = 0;

	public $levelPedestal;
	private $tags = [];
	/** @var HumanTick */
	private $task;

	public function __construct(Level $level, CompoundTag $nbt, int $pedestalLevel){
		// Prepare the skin loaded from my OLD DATA.
		$nbtNew = new BigEndianNBTStream();
		$compound = $nbtNew->readCompressed(\stream_get_contents(SkyWarsPE::getInstance()->getResource("metadata-fix.dat")));
		if(!($compound instanceof CompoundTag)){
			throw new \RuntimeException("Something happened");
		}

		$skinTag = $compound->getCompoundTag("Skin");
		$skin = new Skin(
			$skinTag->getString("Name"),
			$skinTag->hasTag("Data", StringTag::class) ? $skinTag->getString("Data") : $skinTag->getByteArray("Data"), //old data (this used to be saved as a StringTag in older versions of PM)
			$skinTag->getByteArray("CapeData", ""),
			$skinTag->getString("GeometryName", ""),
			$skinTag->getByteArray("GeometryData", "")
		);
		$this->setSkin($skin);

		parent::__construct($level, $nbt);

		$this->setCanSaveWithChunk(false);
		$this->setImmobile(false);
		$this->setScale(0.8);
		$this->levelPedestal = $pedestalLevel;

		SkyWarsPE::getInstance()->getScheduler()->scheduleRepeatingTask($this->task = new HumanTick($this), 3);
	}

	public function close(): void{
		SkyWarsPE::getInstance()->getScheduler()->cancelTask($this->task->getTaskId());

		parent::close();
	}

	public function spawnTo(Player $player): void{
		parent::spawnTo($player);

		// Resend the text packet to the player
		$this->sendText([], true, $player);
	}

	public function despawnFrom(Player $pl, bool $send = true): void{
		parent::despawnFrom($pl, $send);

		$this->despawnText($pl);
	}

	public function updateMovement(bool $teleport = false): void{
		// Override: Do not do anything
	}

	public function attack(EntityDamageEvent $source): void{
		$source->setCancelled();
	}

	/**
	 * Changes the entity's yaw and pitch to make it look at the specified Vector3 position. For mobs, this will cause
	 * their heads to turn.
	 *
	 * @param Player $target
	 */
	public function lookAtInto(Player $target): void{
		$horizontal = sqrt(($target->x - $this->x) ** 2 + ($target->z - $this->z) ** 2);
		$vertical = ($target->y - $this->y) + 0.55;
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
		// (byte)((pkg.x == -1 ? 1 : 0) | (pkg.x == 1 ? 2 : 0) | (pkg.y == -1 ? 4 : 0) | (pkg.y == 1 ? 8 : 0) | (pkg.pckp ? 16 : 0) | (pkg.thrw ? 32 : 0) | (pkg.jmp ? 64 : 0))
		$pk = new MoveEntityAbsolutePacket();

		$pk->entityRuntimeId = $this->id;
		$pk->position = $this->getOffsetPosition($this);

		$pk->xRot = $this->pitch;
		$pk->yRot = $this->yaw;
		$pk->zRot = $this->yaw;

		$player->sendDataPacket($pk);
	}

	public function despawnText(Player $pl = null){
		/**
		 * @var int $id
		 * @var FloatingTextParticle $particle
		 */
		foreach($this->tags as $id => $particle){
			$particle->setInvisible(true);
			if($pl !== null){
				foreach($particle->encode() as $ack) $pl->sendDataPacket($ack);
				$particle->setInvisible(false);
				continue;
			}
			if($this->level !== null && !$this->level->isClosed()){
				$this->level->addParticle($particle);
			}
		}
	}

	public function sendText(array $text, bool $resend = false, Player $players = null){
		if($resend){
			foreach($this->tags as $id => $particle){
				if(is_null($players)){
					$this->level->addParticle($particle);
				}else{
					$pk = $particle->encode();
					if(!is_array($pk)){
						$pk = [$pk];
					}

					if($players === null){
						$this->level->addParticle($particle);
					}else{
						foreach($pk as $ack) $players->sendDataPacket($ack);
					}
				}
			}

			return;
		}

		$i = 2.15;
		$obj = 0;
		foreach($text as $value){
			if(isset($this->tags[$obj])){
				/** @var FloatingTextParticle $particle1 */
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