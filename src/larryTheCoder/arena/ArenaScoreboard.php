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

namespace larryTheCoder\arena;

use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\scoreboard\Scoreboard;
use larryTheCoder\utils\scoreboard\StandardScoreboard;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

/**
 * A Scoreboard interface class
 * This class handles everything regarding to
 * scoreboards.
 *
 * @package larryTheCoder\arena
 */
class ArenaScoreboard extends Task {

	/** @var Player[] */
	private $scoreboards = [];
	/** @var Scoreboard */
	private $sidebarScore;
	private $i = 0;

	public function __construct(){
		SkyWarsPE::$instance->getScheduler()->scheduleRepeatingTask($this, 20);
	}

	public function addPlayer(Player $pl){
		$this->scoreboards[$pl->getName()] = $pl;
		StandardScoreboard::setScore($pl, "§e§lSKYWARS", 1);
	}

	/**
	 * Gets the scoreboard class for this arena,
	 * each arena will be given a separated scoreboard
	 * classes.
	 */
	public function getScoreboard(): Scoreboard{
		return $this->sidebarScore;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		if($this->i > 5){
			$this->i = 0;
		}
		foreach($this->scoreboards as $pl){
			// Scoreboard standards
			StandardScoreboard::setScoreLine($pl, 1, "§e www.hyrulePE.xyz  ");
			StandardScoreboard::setScoreLine($pl, 3, " Mode: §6Solo");
			StandardScoreboard::setScoreLine($pl, 4, " Map: §aSomething");
			StandardScoreboard::setScoreLine($pl, 6, " Kills: §a0");
			StandardScoreboard::setScoreLine($pl, 8, " Players left: §a4");
			StandardScoreboard::setScoreLine($pl, 10, " Refill:  §a2:11");

			// Fuck you mojang.
			StandardScoreboard::setScoreLine($pl, 2, TextFormat::GREEN . "\e");
			StandardScoreboard::setScoreLine($pl, 5, TextFormat::RED . "\e");
			StandardScoreboard::setScoreLine($pl, 7, TextFormat::YELLOW . "\e");
			StandardScoreboard::setScoreLine($pl, 9, TextFormat::BLACK . "\e");
			StandardScoreboard::setScoreLine($pl, 11, TextFormat::LIGHT_PURPLE . "\e");
		}

		$this->i++;
	}
}