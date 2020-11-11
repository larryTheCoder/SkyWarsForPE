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

namespace larryTheCoder\arena\api\utils;


use pocketmine\Player;

class QueueManager {

	/** @var Player[] */
	private $playerQueue = [];

	/**
	 * Attempt to add player into the arena queue. This holds the player queue until the next tick.
	 * This queue will be processed in ArenaTickTask.
	 *
	 * @param Player $player
	 */
	public function addQueue(Player $player): void{
		$this->playerQueue[$player->getName()] = $player;
	}

	public function inQueue(Player $player): bool{
		return isset($this->playerQueue[$player->getName()]);
	}

	public function hasQueue(): bool{
		return !empty($this->playerQueue);
	}

	/**
	 * @return Player[]
	 * @internal
	 */
	public function getQueue(): array{
		if(!empty($queue = $this->playerQueue)){
			$this->playerQueue = [];
		}

		return $queue;
	}

}