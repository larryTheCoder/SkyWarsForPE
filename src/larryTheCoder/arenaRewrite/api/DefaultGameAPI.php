<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2019 larryTheCoder and contributors
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

namespace larryTheCoder\arenaRewrite\api;

use larryTheCoder\arenaRewrite\Arena;
use larryTheCoder\arenaRewrite\tasks\ArenaGameTick;
use larryTheCoder\arenaRewrite\tasks\SignTickTask;
use pocketmine\Player;

/**
 * The runtime handler of the SW game itself. This class handles player
 * actions and controls the arena acts.
 *
 * @package larryTheCoder\arenaRewrite\api
 */
class DefaultGameAPI extends GameAPI {

	public function __construct(Arena $arena){
		parent::__construct($arena);
	}

	public function joinToArena(Player $p): bool{
		// TODO: Implement joinToArena() method.
	}

	public function leaveArena(Player $p): bool{
		// TODO: Implement leaveArena() method.
	}

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1 seconds
	 *
	 * @return array
	 */
	public function getRuntimeTasks(): array{
		return [new ArenaGameTick($this->arena), new SignTickTask($this->arena)];
	}
}