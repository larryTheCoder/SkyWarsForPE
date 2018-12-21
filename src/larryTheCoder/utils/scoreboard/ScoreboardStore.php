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

namespace larryTheCoder\utils\scoreboard;

use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;

/**
 * Code being reuse as the source code is licensed
 * under GNU General Public License v3.0 which allows
 * to use code without explicit permission from the author.
 * - Commercial use
 * - Modification
 * - Distribution
 * - Patent use
 * - Private use
 *
 * @package larryTheCoder\utils\scoreboard
 * @author Miste
 */
class ScoreboardStore {

	/** @var array */
	private $entries;
	/** @var array */
	private $scoreboards;
	/** @var array */
	private $displaySlots;
	/** @var array */
	private $sortOrders;
	/** @var array */
	private $ids;
	/** @var array */
	private $viewers;

	/**
	 * Adds an entry on a scoreboard.
	 *
	 * @param string $objectiveName
	 * @param int $line
	 * @param ScorePacketEntry $entry
	 */
	public function addEntry(string $objectiveName, int $line, ScorePacketEntry $entry){
		$this->entries[$objectiveName][$line] = $entry;
	}

	/**
	 * Removes a scoreboard entry from the list
	 * This removes the specific line of it.
	 *
	 * @param string $objectiveName The identification of the scoreboard
	 * @param int $line The line of the scoreboard
	 */
	public function removeEntry(string $objectiveName, int $line){
		unset($this->entries[$objectiveName][$line]);
	}

	/**
	 * Register the scoreboard on the list.
	 *
	 * @param string $objectiveName The identification of the scoreboard
	 * @param string $displayName The scoreboard name on specific line
	 * @param string $displaySlot The scoreboard line that the name will shown.
	 * @param int $sortOrder The order of the scoreboard, 0: ascending/1: descending
	 * @param int $scoreboardId The random scoreboard ID.
	 */
	public function registerScoreboard(string $objectiveName, string $displayName, string $displaySlot, int $sortOrder, int $scoreboardId){
		$this->entries[$objectiveName] = null;
		$this->scoreboards[$displayName] = $objectiveName;
		$this->displaySlots[$objectiveName] = $displaySlot;
		$this->sortOrders[$objectiveName] = $sortOrder;
		$this->ids[$objectiveName] = $scoreboardId;
		$this->viewers[$objectiveName] = [];
	}

	/**
	 * Unregister a scoreboard from the list.
	 *
	 * @param string $objectiveName The scoreboard objective name.
	 * @param string $displayName The display name of it
	 */
	public function unregisterScoreboard(string $objectiveName, string $displayName){
		unset($this->entries[$objectiveName]);
		unset($this->scoreboards[$displayName]);
		unset($this->displaySlots[$objectiveName]);
		unset($this->sortOrders[$objectiveName]);
		unset($this->ids[$objectiveName]);
		unset($this->viewers[$objectiveName]);
	}

	/**
	 * @param string $objectiveName
	 *
	 * @return array
	 */
	public function getEntries(string $objectiveName): array{
		return $this->entries[$objectiveName];
	}

	/**
	 * @param string $objectiveName
	 * @param int $line
	 *
	 * @return bool
	 */

	public function entryExist(string $objectiveName, int $line): bool{
		return isset($this->entries[$objectiveName][$line]);
	}

	/**
	 * @param string $displayName
	 *
	 * @return string|null
	 */

	public function getId(string $displayName){
		return $this->scoreboards[$displayName] ?? null;
	}

	/**
	 * @param string $objectiveName
	 *
	 * @return string
	 */

	public function getDisplaySlot(string $objectiveName): string{
		return $this->displaySlots[$objectiveName];
	}

	/**
	 * @param string $objectiveName
	 *
	 * @return int
	 */

	public function getSortOrder(string $objectiveName): int{
		return $this->sortOrders[$objectiveName];
	}

	/**
	 * @param string $objectiveName
	 *
	 * @return int
	 */

	public function getScoreboardId(string $objectiveName): int{
		return $this->ids[$objectiveName];
	}

	/**
	 * @param string $objectiveName
	 * @param string $playerName
	 */

	public function addViewer(string $objectiveName, string $playerName){
		if(!in_array($playerName, $this->viewers[$objectiveName])){
			array_push($this->viewers[$objectiveName], $playerName);
		}
	}

	/**
	 * @param string $objectiveName
	 * @param string $playerName
	 */

	public function removeViewer(string $objectiveName, string $playerName){
		if(in_array($playerName, $this->viewers[$objectiveName])){
			if(($key = array_search($playerName, $this->viewers[$objectiveName])) !== false){
				unset($this->viewers[$objectiveName][$key]);
			}
		}
	}

	/**
	 * @param string $objectiveName
	 *
	 * @return string[]|null
	 */
	public function getViewers(string $objectiveName): ?array{
		return $this->viewers[$objectiveName] ?? null;
	}

	/**
	 * @param string $oldName
	 * @param string $newName
	 */

	public function rename(string $oldName, string $newName){
		$this->scoreboards[$newName] = $this->scoreboards[$oldName];
		unset($this->scoreboards[$oldName]);
	}

	/**
	 * @param string $playerName
	 */

	public function removePotentialViewer(string $playerName){
		foreach($this->viewers as $name => $data){
			if(in_array($playerName, $data)){
				if(($key = array_search($playerName, $data)) !== false){
					unset($this->viewers[$name][$key]);
				}
			}
		}
	}

	/**
	 * @param string $displayName
	 *
	 * @return string|null
	 */

	public function getScoreboardName(string $displayName): ?string{
		return $this->scoreboards[$displayName] ?? null;
	}
}