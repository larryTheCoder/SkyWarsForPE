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

namespace larryTheCoder\utils\fireworks\entity;

use larryTheCoder\utils\fireworks\Fireworks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\entity\projectile\Projectile;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\utils\Random;

class FireworksRocket extends Projectile {

	public const NETWORK_ID = EntityIds::FIREWORKS_ROCKET;

	public const DATA_DISPLAY_ITEM = 16; //int (id | (data << 16))
	public const DATA_DISPLAY_OFFSET = 17; //int
	public const DATA_HAS_DISPLAY = 18; //byte (must be 1 for minecart to show block inside)

	public $width = 0.25;
	public $height = 0.25;
	/** @var Fireworks */
	public $fireworksItem;
	/** @var int */
	public $lifeTime;
	/** @var float */
	protected $gravity = 0.0;
	/** @var float */
	protected $drag = 0.1;

	public function __construct(Level $level, CompoundTag $nbt, Fireworks $fireworks, Entity $shootingEntity = null){
		$this->fireworksItem = $fireworks;
		$random = new Random();
		$flyTime = 1;
		$lifeTime = null;
		parent::__construct($level, $nbt, $shootingEntity);

		if($nbt->hasTag("Fireworks", CompoundTag::class)){
			$fireworkCompound = $nbt->getCompoundTag("Fireworks");
			$flyTime = $fireworkCompound->getByte("Flight", 1);
			$lifeTime = $fireworkCompound->getInt("LifeTime", 10 * $flyTime + $random->nextBoundedInt(6) + $random->nextBoundedInt(7));
		}

		$this->lifeTime = $lifeTime ?? 10 * $flyTime + $random->nextBoundedInt(6) + $random->nextBoundedInt(7);

		$this->motion->x = ($this->nextGaussian() * 0.001);
		$this->motion->z = ($this->nextGaussian() * 0.001);
		$this->motion->y = 0.05;

		$nbt->setInt("Life", $this->lifeTime);
		$nbt->setInt("LifeTime", $this->lifeTime);
	}

	public function nextGaussian(){
		$v = 2;
		$u1 = 0;
		while($v > 1){
			$u1 = rand(0, 9999) / 9999;
			$u2 = rand(0, 9999) / 9999;

			$v = (2 * $u1 - 1) * (2 * $u1 - 1) + (2 * $u2 - 1) * (2 * $u2 - 1);
		}

		return (2 * $u1 - 1) * ((-2 * log($v) / $v) ^ 0.5);
	}

	public function spawnTo(Player $player): void{
		$this->setMotion($this->getDirectionVector());
		$this->level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_LAUNCH);
		parent::spawnTo($player);
	}

	public function despawnFromAll(): void{
		$this->broadcastEntityEvent(EntityEventPacket::FIREWORK_PARTICLES, 0);
		parent::despawnFromAll();
		$this->level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_BLAST);
	}

	public function entityBaseTick(int $tickDiff = 1): bool{
		if($this->lifeTime-- <= 0){
			$this->flagForDespawn();
		}else{
			$this->motion->y += 0.04;
			$this->updateMovement();

			$f = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
			$this->yaw = atan2($this->motion->x, $this->motion->z) * (180 / M_PI);
			$this->pitch = atan2($this->motion->y, $f) * (180 / M_PI);

			return true;
		}

		return true;
	}

	protected function initEntity(): void{
		$this->setGenericFlag(self::DATA_FLAG_AFFECTED_BY_GRAVITY, true);
		$this->setGenericFlag(self::DATA_FLAG_HAS_COLLISION, true);
		$this->propertyManager->setItem(self::DATA_DISPLAY_ITEM, $this->fireworksItem);
		$this->propertyManager->setInt(self::DATA_DISPLAY_OFFSET, 1);
		$this->propertyManager->setByte(self::DATA_HAS_DISPLAY, 1);

		parent::initEntity();
	}
}
