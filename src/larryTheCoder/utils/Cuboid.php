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
use pocketmine\math\Vector3;

class Cuboid {

	private $x1 = 0;
	private $y1 = 0;
	private $z1 = 0;
	private $x2 = 0;
	private $y2 = 0;
	private $z2 = 0;

	public function __construct(Vector3 $locationMin, Vector3 $locationMax){
		$this->x1 = min($locationMin->getFloorX(), $locationMax->getFloorX());
		$this->y1 = min($locationMin->getFloorY(), $locationMax->getFloorY());
		$this->z1 = min($locationMin->getFloorZ(), $locationMax->getFloorZ());
		$this->x2 = max($locationMin->getFloorX(), $locationMax->getFloorX());
		$this->y2 = max($locationMin->getFloorY(), $locationMax->getFloorY());
		$this->z2 = max($locationMin->getFloorZ(), $locationMax->getFloorZ());
	}

	public function contains(Location $location): bool{
		return $location->getFloorX() >= $this->x1 && $location->getFloorX() <= $this->x2 && $location->getFloorY() > $this->y1 && $location->getFloorY() < $this->y2 && $location->getFloorZ() >= $this->z1 && $location->getFloorZ() <= $this->z2;
	}

	public function getSize(): int{
		return ($this->x2 - $this->x1 + 1) * ($this->y2 - $this->y1 + 1) * ($this->z2 - $this->z1 + 1);
	}

	/**
	 * Get the array of numbers designed by number
	 * or count
	 *
	 * @param int $count
	 * @return Vector3[]
	 */
	public function explicit(int $count = 3): array{
		$loc = [];
		for($i = 1; $i <= $count; $i++){
			$loc[] = new Vector3($this->x2 / $i, 0, $this->z2 / $i);
			$loc[] = new Vector3($this->x1 / $i, 0, $this->z1 / $i);
		}

		return $loc;
	}


}