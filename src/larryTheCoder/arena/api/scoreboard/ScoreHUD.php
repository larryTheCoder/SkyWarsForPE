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

namespace larryTheCoder\arena\api\scoreboard;

use larryTheCoder\arena\api\impl\Scoreboard;
use pocketmine\Player;

/**
 * ScoreHUD compatibility layer class, allows communication with this plugin
 * and perhaps improve gameplay experience as v6.0 allows event-driven scoreboards.
 */
class ScoreHUD implements Scoreboard {

	public function __construct(){
		// TODO: Figure out how to gain dominance in this haha.
	}

	public function setStatus(string $status): void{
		// TODO: Implement tickScoreboard() method.
	}


	public function tickScoreboard(): void{
		// TODO: Implement tickScoreboard() method.
	}

	public function resetScoreboard(): void{
		// TODO: Implement resetScoreboard() method.
	}

	public function removePlayer(Player $pl): void{
		// TODO: Implement removePlayer() method.
	}

	public function addPlayer(Player $pl): void{
		// TODO: Implement addPlayer() method.
	}
}