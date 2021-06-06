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

use Ifera\ScoreHud\libs\jackmd\scorefactory\ScoreFactory;
use Ifera\ScoreHud\ScoreHud;
use Ifera\ScoreHud\utils\HelperUtils;
use larryTheCoder\arena\api\Arena;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

/**
 * ScoreHUD compatibility layer class, allows communication with this plugin
 * and perhaps improve gameplay experience as v6.0 allows event-driven scoreboards.
 */
class ScoreFilter extends Internal {

	/** @var ScoreHud|null */
	public $scoreboard = null;

	public function __construct(Arena $arena, Config $defaultConf){
		parent::__construct($arena, $defaultConf);

		// TODO: Check for v6.0, event driven scoreboards, wooo. For now lets stick to v5.2.0
		//       which is literally discarding or ignoring this plugin functionality.
		$scoreboard = Server::getInstance()->getPluginManager()->getPlugin("ScoreHud");
		if($scoreboard !== null && $scoreboard instanceof ScoreHud){
			$this->scoreboard = $scoreboard;
		}
	}

	public function removePlayer(Player $player): void{
		if($this->scoreboard !== null){
			HelperUtils::destroy($player);
		}

		parent::removePlayer($player);
	}

	public function addPlayer(Player $player): void{
		if($this->scoreboard !== null){
			ScoreFactory::removeScore($player);
			HelperUtils::disable($player);
		}

		parent::addPlayer($player);
	}
}