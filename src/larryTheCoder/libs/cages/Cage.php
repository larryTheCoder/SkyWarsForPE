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

namespace larryTheCoder\libs\cages;

use pocketmine\block\Block;
use pocketmine\level\Position;

/**
 * The cage main class
 *
 * Class Cage
 * @package larryTheCoder\cages
 */
class Cage {

	/** @var Block[] */
	private $parts;
	private $cageName;
	private $value;

	/**
	 * Cage constructor.
	 * @param String $name
	 * @param int $value
	 * @param Block[] $parts
	 */
	public function __construct(String $name, int $value, array $parts){
		$this->parts = $parts;
		$this->cageName = $name;
		$this->value = $value;
	}

	/**
	 * @param Position $locate
	 * @return Position[]
	 */
	public function build(Position $locate): array{
		$loc = clone $locate;
		$this->clearObstacle(clone $locate);
		$list = [];
		$level = $loc->getLevel();
		$part = $this->parts;
		$loc->y = $loc->y - 1;
		$list[] = $loc->asVector3();
		$level->setBlock($loc->asVector3(), $part[0], true, true);
		for($i = 0; $i <= 4; $i++){
			$array = [
				$loc->add(1.0, 0.0, 0.0),
				$loc->add(-1.0, 0.0, 0.0),
				$loc->add(0.0, 0.0, 1.0),
				$loc->add(0.0, 0.0, -1.0),
				$loc->add(-1.0, 0.0, -1.0),
				$loc->add(1.0, 0.0, 1.0),
				$loc->add(-1.0, 0.0, 1.0),
				$loc->add(1.0, 0.0, -1.0),
			];
			for($j = 0; $j < count($array); ++$j){
				$loc2 = $array[$j];
				$list[] = $loc2;
				$level->setBlock($loc2, $part[$i], true, true);
			}
			$loc->y = $loc->y + 1;
		}
		$loc->y = $loc->y - 1;
		$list[] = $loc->asVector3();
		$level->setBlock($loc->asVector3(), $part[4], true, true);

		return $list;
	}

	public function clearObstacle(Position $loc){
		$loc->y = $loc->y - 1;
		for($y = 0; $y < 6; ++$y){
			for($z = -2; $z < 2; $z++){
				for($x = -2; $x < 2; $x++){
					$loc->level->setBlock($loc->add($x, $y, $z), Block::get(0));
				}
			}
		}
	}

	/**
	 * @return String
	 */
	public function getCageName(): String{
		return $this->cageName;
	}

	/**
	 * @return int
	 */
	public function getPrice(): int{
		return $this->value;
	}

}