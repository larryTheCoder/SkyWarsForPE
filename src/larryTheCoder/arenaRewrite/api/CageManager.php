<?php
/*
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

declare(strict_types = 1);

namespace larryTheCoder\arenaRewrite\api;

use pocketmine\math\Vector3;
use pocketmine\Player;

/**
 * Class CageManager
 * @package larryTheCoder\arenaRewrite\api
 */
class CageManager {

	/** @var Vector3[] */
	protected $claimedCages = [];
	/** @var Vector3[] */
	private $cages;

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
	public function setCage(Player $player): ?Vector3{
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
	 * @param Player $player
	 * @return Vector3|null
	 */
	public function getCage(Player $player): ?Vector3{
		if(!isset($this->claimedCages[$player->getName()])) return null;

		return $this->claimedCages[$player->getName()];
	}

	/**
	 * Attempt to teleport all players to the cage.
	 */
	public function teleportToCages(): void{
		// NOOP
	}

	/**
	 * self-explanatory
	 */
	public function resetAll(){
		foreach($this->claimedCages as $vec) $this->cages[] = $vec;
		$this->claimedCages = [];
	}
}