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

namespace larryTheCoder\arena\api\impl;


use pocketmine\Player;

interface Scoreboard {

	/**
	 * Ticks scoreboard, this function will be called to
	 * update all player's scoreboard.
	 */
	public function tickScoreboard(): void;

	/**
	 * Reset all player's scoreboard, this indicates that the arena
	 * has finished and all player's scoreboard must be unset.
	 */
	public function resetScoreboard(): void;

	/**
	 * Removes a player from this scoreboard.
	 *
	 * @param Player $player
	 */
	public function removePlayer(Player $player): void;

	/**
	 * Add a player into this scoreboard.
	 *
	 * @param Player $player
	 */
	public function addPlayer(Player $player): void;

	/**
	 * Set current event/status of an arena.
	 *
	 * @param string $status
	 */
	public function setStatus(string $status): void;
}