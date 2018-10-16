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

use pocketmine\level\particle\GenericParticle;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;

class ParticleFlowTask extends Task {

	/** @var Position */
	private $location;
	/** @var int */
	private $time = 0;
	/** @var int */
	private $aroundHelix = 0;
	/** @var int */
	private $particleID;

	public function __construct(Position $loc, int $particleID){
		$this->location = $loc;
		$this->particleID = $particleID;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		$loc = $this->location;
		$x = sin(-0.39269908169872414 * $this->time) * -0.5;
		$y = 0.01 * $this->aroundHelix;
		$z = cos(-0.39269908169872414 * $this->time) * -0.5;
		$v = new Vector3($x, $y, $z);
		$loc2 = $loc->add($v);
		++$this->time;
		++$this->aroundHelix;
		if($this->aroundHelix >= 100){
			$this->aroundHelix = 0.0;
		}

		$this->location->getLevel()->addParticle(new GenericParticle($loc2, $this->particleID));
	}
}