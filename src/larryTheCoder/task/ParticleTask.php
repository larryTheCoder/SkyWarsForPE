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


use Exception;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\level\particle\{GenericParticle, Particle};
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\tile\Chest;

class ParticleTask extends Task {

	/** @var Chest */
	private $chest;
	/** @var int */
	private $lifeTick = 0;

	/**
	 * ParticleTask constructor.
	 * @param Chest $chest
	 */
	public function __construct(Chest $chest){
		$this->chest = $chest;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		$this->lifeTick++;
		if(!$this->chest->isValid() || $this->lifeTick >= 360){
			SkyWarsPE::getInstance()->getScheduler()->cancelTask($this->getTaskId());

			return;
		}
		$i = 0;
		try{
			$i = Utils::getParticleTimer($this->chest->getId());
		}catch(Exception $ex){
			Utils::setParticleTimer($this->chest->getId(), 0);
		}

		$loc = Utils::getLocation(Utils::getParticleTimer($this->chest->getId()), 1, $this->chest);
		$info = Utils::helixMath($this->chest->getId());
		if($info == null){
			Utils::setHelixMath($this->chest->getId(), 0, 0);

			return;
		}
		$y = $info[0];
		$up = $info[1];
		if($y <= 2.0){
			$up = 1.0;
		}
		if($y == 0.0){
			$up = 0.0;
		}
		if($up == 0.0){
			$y += 0.125;
		}
		if($up == 1.0){
			$y -= 0.125;
		}
		$this->chest->getLevel()->addParticle(new GenericParticle(new Vector3($loc->getX() + 0.5, $loc->getY() + $y, $loc->getZ() + 0.5), Particle::TYPE_REDSTONE));
		Utils::setHelixMath($this->chest->getId(), $y, $up);
		if($i == 30){
			Utils::setParticleTimer($this->chest->getId(), 0);
		}else{
			Utils::setParticleTimer($this->chest->getId(), $i + 1);
		}
	}
}