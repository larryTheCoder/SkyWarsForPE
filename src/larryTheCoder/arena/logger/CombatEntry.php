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

namespace larryTheCoder\arena\logger;

use pocketmine\event\entity\EntityDamageEvent;

class CombatEntry {

	/** @var string */
	public $playerName;
	/** @var string|null */
	public $attackFrom;
	/** @var int */
	public $lastAttack;
	/** @var int */
	public $attackId = EntityDamageEvent::CAUSE_MAGIC;

	/**
	 * Creates an entry of the last player being damaged, this entry will be used to determine the right player damager
	 * that has attacked their target, providing consistency and reliability in matchmaking.
	 *
	 * @param string $target
	 * @param int $attackId
	 * @param string|null $from
	 * @return CombatEntry
	 */
	public static function fromEntry(string $target, int $attackId = EntityDamageEvent::CAUSE_MAGIC, ?string $from = null): CombatEntry{
		$entry = new CombatEntry();
		$entry->playerName = $target;
		$entry->attackFrom = $from;
		$entry->attackId = $attackId;
		$entry->lastAttack = time();

		return $entry;
	}
}