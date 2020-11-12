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


class CombatLogger {

	public const MAX_ENTRY = 50;

	/** @var CombatEntry[][] */
	public $players = [];

	/**
	 * Combat logger entry, this entries will be pushed into an array and the data will be stored
	 * until it has been garbage collected.
	 *
	 * @param CombatEntry $entry
	 */
	public function addEntry(CombatEntry $entry): void{
		$this->players[$entry->playerName][] = $entry;

		// Automatic garbage collection, providing consistence array size at all cost.
		if(($entryCount = count($data = $this->players[$entry->playerName])) >= self::MAX_ENTRY){
			$reverse = array_reverse($data, true);
			foreach($reverse as $key => $entry){
				unset($this->players[$entry->playerName][$key]);

				if(--$entryCount < self::MAX_ENTRY) return;
			}
		}
	}

	/**
	 * Retrieves combat entry from the latest entry array.
	 *
	 * @param string $playerName
	 * @param int $lastAttack The
	 * @return CombatEntry|null
	 */
	public function getEntry(string $playerName, int $lastAttack): ?CombatEntry{
		$entry = $this->players[$playerName] ?? null;

		if($entry === null) return null;

		/** @var CombatEntry|null $targetEntry */
		$targetEntry = null;

		$reverse = array_reverse($entry);
		foreach($reverse as $key => $item){
			if($item->lastAttack >= (time() - $lastAttack)){
				if($targetEntry === null || $targetEntry->attackFrom === null){
					$targetEntry = $item;
				}
			}
		}

		return $targetEntry;
	}

	public function resetAll(): void{
		$this->players = [];
	}
}