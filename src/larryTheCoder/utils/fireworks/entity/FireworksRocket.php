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
use pocketmine\entity\projectile\Projectile;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class FireworksRocket extends Projectile {

	public const NETWORK_ID = self::FIREWORKS_ROCKET;

	public $width = 0.25;
	public $height = 0.25;

	/** @var int */
	protected $lifeTime = 0;

	public function __construct(Level $level, CompoundTag $nbt, ?Fireworks $fireworks = null){
		parent::__construct($level, $nbt);
		if($fireworks !== null && $fireworks->getNamedTagEntry("Fireworks") instanceof CompoundTag){
			$this->propertyManager->setCompoundTag(self::DATA_MINECART_DISPLAY_BLOCK, $fireworks->getNamedTag());
			$this->setLifeTime($fireworks->getRandomizedFlightDuration());
		}
		$level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_LAUNCH);
		$this->setCanSaveWithChunk(false);
	}

	protected function tryChangeMovement(): void{
		$this->motion->x *= 1.15;
		$this->motion->y += 0.04;
		$this->motion->z *= 1.15;
	}

	public function entityBaseTick(int $tickDiff = 1): bool{
		if($this->closed){
			return false;
		}
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->doLifeTimeTick()){
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function setLifeTime(int $life): void{
		$this->lifeTime = $life;
	}

	protected function doLifeTimeTick(): bool{
		if(!$this->isFlaggedForDespawn() and --$this->lifeTime < 0){
			$this->doExplosionAnimation();
			$this->flagForDespawn();

			return true;
		}

		return false;
	}

	protected function doExplosionAnimation(): void{
		$fireworks_nbt = $this->propertyManager->getCompoundTag(self::DATA_MINECART_DISPLAY_BLOCK);
		if($fireworks_nbt === null){
			return;
		}
		$fireworks_nbt = $fireworks_nbt->getCompoundTag("Fireworks");
		if($fireworks_nbt === null){
			return;
		}
		$explosions = $fireworks_nbt->getListTag("Explosions");
		if($explosions === null){
			return;
		}
		/** @var CompoundTag $explosion */
		foreach($explosions->getAllValues() as $explosion){
			switch($explosion->getByte("FireworkType")){
				case Fireworks::TYPE_SMALL_SPHERE:
					$this->level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_BLAST);
					break;
				case Fireworks::TYPE_HUGE_SPHERE:
					$this->level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_LARGE_BLAST);
					break;
				case Fireworks::TYPE_STAR:
				case Fireworks::TYPE_BURST:
				case Fireworks::TYPE_CREEPER_HEAD:
					$this->level->broadcastLevelSoundEvent($this, LevelSoundEventPacket::SOUND_TWINKLE);
					break;
			}
		}
		$this->broadcastEntityEvent(ActorEventPacket::FIREWORK_PARTICLES);
	}
}
