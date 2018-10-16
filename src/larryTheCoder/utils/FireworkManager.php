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

namespace larryTheCoder\utils;

use pocketmine\level\Location;
use pocketmine\Player;

/**
 * This package handle the random E-84
 * Firework Pattern which looks awesome.
 *
 * Class FireworkManager
 * @package larryTheCoder\utils
 */
class FireworkManager {

	/** @var int */
	public $side = 0;
	/** @var Location[] */
	public $conclude = [];
	/** @var Location */
	public $currentLoc = null;
	/** @var Player */
	private $player;
	/** @var float|int */
	private $currentY = 0;

	public function __construct(Player $player){
		$this->player = $player;
		$this->currentY = $player->getY();
	}

	public function display(){
		$cuboid = $this->cuboidConductive()->explicit(10);
		foreach($cuboid as $val => $vec){
			$location = Location::fromObject($vec, $this->player->getLevel(), 0, 0);
			Utils::addFireworks($location);
			unset($cuboid[$val]);
		}
	}

	/**
	 * @return Cuboid
	 */
	public function cuboidConductive(){
		$facing = $this->player->getDirection();
		$vec = $this->player->getSide($facing, 15);
		$vec1 = $vec->asVector3()->setComponents($vec->getX() - 10, $vec->getY(), $vec->getZ() - 10);
		$vec2 = $vec->asVector3()->setComponents($vec->getX() + 10, $vec->getY(), $vec->getZ() + 10);

		return new Cuboid($vec1, $vec2);
	}

}