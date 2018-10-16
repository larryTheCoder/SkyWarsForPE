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

namespace larryTheCoder\utils\fireworks;

use larryTheCoder\utils\fireworks\entity\FireworksRocket;
use pocketmine\level\Position;
use pocketmine\utils\Random;

class FireworksData {

	public const TYPE_SMALL_BALL = 0;
	public const TYPE_LARGE_BALL = 1;
	public const TYPE_STAR_SHAPED = 2;
	public const TYPE_CREEPER_SHAPED = 3;
	public const TYPE_BURST = 4;

	public const COLOR_BLACK = 0;
	public const COLOR_RED = 1;
	public const COLOR_GREEN = 2;
	public const COLOR_BROWN = 3;
	public const COLOR_BLUE = 4;
	public const COLOR_PURPLE = 5;
	public const COLOR_CYAN = 6;
	public const COLOR_LIGHT_GRAY = 7;
	public const COLOR_GRAY = 8;
	public const COLOR_PINK = 9;
	public const COLOR_LIME = 10;
	public const COLOR_YELLOW = 11;
	public const COLOR_LIGHT_BLUE = 12;
	public const COLOR_MAGENTA = 13;
	public const COLOR_ORANGE = 14;
	public const COLOR_WHITE = 15;

	/** @var int */
	public $fireworkColor = self::COLOR_GREEN;
	/** @var int */
	public $fireworkFade = self::COLOR_GREEN;
	/** @var bool */
	public $fireworkFlicker = true;
	/** @var bool */
	public $fireworkTrail = true;
	/** @var int */
	public $fireworkType = self::TYPE_SMALL_BALL;
	/** @var int */
	public $flight = 1;

	public function setFireworkColor(int $fireworkColor){
		$this->fireworkColor = $fireworkColor;
	}

	public function setFireworkFade(int $fireworkFade){
		$this->fireworkFade = $fireworkFade;
	}

	public function getFireworkEntity(Position $base): FireworksRocket{
		$random = new Random();
		$yaw = $random->nextBoundedInt(360);
		$pitch = -1 * (float)(90 + ($random->nextFloat() * 5 - 5 / 2));

		$firework = new Fireworks();
		$nbt = Fireworks::ToNbt($this, $base, $yaw, $pitch);
		$firework->setNamedTag($nbt);

		return new FireworksRocket($base->getLevel(), $nbt, $firework, null);
	}

	public function random(int $fireworkColor = -1, int $fireworkType = -1){
		if($fireworkColor === -1){
			$this->fireworkColor = rand(1, 15);
		}else{
			$this->fireworkColor = $fireworkColor;
		}
		if($fireworkType === -1){
			$this->fireworkType = rand(0, 4);
		}else{
			$this->fireworkColor = $fireworkType;
		}
	}

	public function reset(){
		$this->fireworkColor = 0;
		$this->fireworkFade = 0;
		$this->fireworkType = 0;
		$this->fireworkFlicker = false;
		$this->fireworkTrail = false;
	}
}