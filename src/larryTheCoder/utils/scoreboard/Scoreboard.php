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

use larryTheCoder\utils\Utils;
use pocketmine\network\mcpe\protocol\{RemoveObjectivePacket, SetDisplayObjectivePacket, SetScorePacket};
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;
use pocketmine\Server;

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
 * @rewrite larryTheCoder
 */
class Scoreboard {

	const MAX_LINES = 15;

	/** @var string */
	private $objectiveName;
	/** @var string */
	private $displayName;
	/** @var string */
	private $displaySlot;
	/** @var int */
	private $sortOrder;
	/** @var int */
	private $scoreboardId;

	public function __construct(string $title, int $action){
		$this->displayName = $title;
		if($action === Action::CREATE && is_null(Utils::getStore()->getId($title))){
			$this->objectiveName = uniqid();

			return;
		}
		$this->objectiveName = Utils::getStore()->getId($title);
		$this->displaySlot = Utils::getStore()->getDisplaySlot($this->objectiveName);
		$this->sortOrder = Utils::getStore()->getSortOrder($this->objectiveName);
		$this->scoreboardId = Utils::getStore()->getScoreboardId($this->objectiveName);
	}

	/**
	 * Displays this scoreboard to a player,
	 * The player will be linked to metadata store
	 * once its stored.
	 *
	 * @param $player
	 */
	public function addDisplay(Player $player){
		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = $this->displaySlot;
		$pk->objectiveName = $this->objectiveName;
		$pk->displayName = $this->displayName;
		$pk->criteriaName = "dummy";
		$pk->sortOrder = $this->sortOrder;
		$player->sendDataPacket($pk);

		Utils::getStore()->addViewer($this->objectiveName, $player->getName());

		if($this->displaySlot === DisplaySlot::BELOWNAME){
			$player->setScoreTag($this->displayName);
		}
	}

	/**
	 * Removes a scoreboard from being displayed
	 * to the player, its will be automatically
	 * removed from metadata store once removed.
	 *
	 * @param $player
	 */
	public function removeDisplay(Player $player){
		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->objectiveName;
		$player->sendDataPacket($pk);

		Utils::getStore()->removeViewer($this->objectiveName, $player->getName());
	}

	/**
	 * Set the message for specific line. These will be
	 * sent to the player that have been linked to it.
	 *
	 * @param Player $player
	 * @param int $line
	 * @param string $message
	 */
	public function setLine(Player $player, int $line, string $message){
		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_REMOVE;

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $this->objectiveName;
		$entry->score = self::MAX_LINES - $line;
		$entry->scoreboardId = ($this->scoreboardId + $line);
		$pk->entries[] = $entry;
		$player->sendDataPacket($pk);


		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_CHANGE;

		if(!Utils::getStore()->entryExist($this->objectiveName, ($line - 2)) && $line !== 1){
			for($i = 1; $i <= ($line - 1); $i++){
				if(!Utils::getStore()->entryExist($this->objectiveName, ($i - 1))){
					$entry = new ScorePacketEntry();
					$entry->objectiveName = $this->objectiveName;
					$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
					$entry->customName = str_repeat(" ", $i); //You can't send two lines with the same message
					$entry->score = self::MAX_LINES - $i;
					$entry->scoreboardId = ($this->scoreboardId + $i - 1);
					$pk->entries[] = $entry;
					Utils::getStore()->addEntry($this->objectiveName, ($i - 1), $entry);
				}
			}
		}

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $this->objectiveName;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->customName = $message;
		$entry->score = self::MAX_LINES - $line;
		$entry->scoreboardId = ($this->scoreboardId + $line);
		$pk->entries[] = $entry;
		Utils::getStore()->addEntry($this->objectiveName, ($line - 1), $entry);
		$player->sendDataPacket($pk);
	}

	/**
	 * Removes a line from being viewed to all
	 * the players, once removed, the player
	 * linked on this scoreboard won't be able
	 * to see it.
	 *
	 * @param Player $player
	 * @param int $line
	 */
	public function removeLine(Player $player, int $line){
		$pk = new SetScorePacket();
		$pk->type = SetScorePacket::TYPE_REMOVE;

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $this->objectiveName;
		$entry->score = self::MAX_LINES - $line;
		$entry->scoreboardId = ($this->scoreboardId + $line);
		$pk->entries[] = $entry;
		$player->sendDataPacket($pk);

		Utils::getStore()->removeEntry($this->objectiveName, $line);
	}

	/**
	 * @param string $displaySlot
	 * @param int $sortOrder
	 */
	public function create(string $displaySlot, int $sortOrder){
		$this->displaySlot = $displaySlot;
		$this->sortOrder = $sortOrder;
		$this->scoreboardId = mt_rand(1, 100000);
		Utils::getStore()->registerScoreboard($this->objectiveName, $this->displayName, $this->displaySlot, $this->sortOrder, $this->scoreboardId);
	}

	public function delete(){
		Utils::getStore()->unregisterScoreboard($this->objectiveName, $this->displayName);
	}

	/**
	 * Renames the display name for this scoreboard.
	 *
	 * @param string $newName The new name for this.
	 */
	public function rename(string $newName){
		// Miste? Ever heard about Logic?
		Utils::getStore()->rename($this->displayName, $newName);

		$this->displayName = $newName;

		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $this->objectiveName;

		$pk2 = new SetDisplayObjectivePacket();
		$pk2->displaySlot = $this->displaySlot;
		$pk2->objectiveName = $this->objectiveName;
		$pk2->displayName = $this->displayName;
		$pk2->criteriaName = "dummy";
		$pk2->sortOrder = $this->sortOrder;

		$pk3 = new SetScorePacket();
		$pk3->type = SetScorePacket::TYPE_CHANGE;
		foreach(Utils::getStore()->getEntries($this->objectiveName) as $index => $entry){
			$pk3->entries[$index] = $entry;
		}

		foreach(Utils::getStore()->getViewers($this->objectiveName) as $name){
			$p = Server::getInstance()->getPlayer($name);
			$p->sendDataPacket($pk);
			$p->sendDataPacket($pk2);
			$p->sendDataPacket($pk3);
		}
	}

	/**
	 * @return string[]
	 */
	public function getViewers(): array{
		return Utils::getStore()->getViewers($this->objectiveName);
	}
}