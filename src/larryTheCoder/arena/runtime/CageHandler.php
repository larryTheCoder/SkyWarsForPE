<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
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

namespace larryTheCoder\arena\runtime;

use pocketmine\math\Vector3;
use pocketmine\Player;

class CageHandler {

	/** @var Vector3[] */
	private $cages;

	/** @var Vector3[] */
	private $claimedCages = [];

	public function __construct(array $cages){
		$this->cages = $cages;
	}

	/**
	 * Retrieves the next available cages that will be used in the game.
	 * This method is to allocate cages after the player left.
	 *
	 * @param Player $player
	 * @return Vector3|null
	 */
	public function nextCage(Player $player): ?Vector3{
		if(empty($this->cages)) return null; // Cages are full.

		return $this->claimedCages[$player->getName()] = array_pop($this->cages);
	}

	/**
	 * Remove the owned cage from the given player.
	 *
	 * @param Player $player
	 */
	public function removeCage(Player $player): void{
		if(!isset($this->claimedCages[$player->getName()])) return;

		$this->cages[] = $this->claimedCages[$player->getName()];

		unset($this->claimedCages[$player->getName()]);
	}

	/**
	 * self-explanatory
	 */
	public function resetAll(){
		foreach($this->claimedCages as $vec) $this->cages[] = $vec;
		$this->claimedCages = [];
	}
}