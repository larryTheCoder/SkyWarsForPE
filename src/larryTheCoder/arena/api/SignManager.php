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

namespace larryTheCoder\arena\api;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

/**
 * Manages sign for update and changes.
 *
 * @package larryTheCoder\arenaRewrite\api
 */
class SignManager {

	/** @var Sign */
	private $signTile;
	/** @var Arena */
	private $arena;

	/** @var string[] */
	private $cache = [];
	/** @var string[] */
	private $updatedSign = [];

	public function __construct(Arena $arena, Position $tilePosition){
		$this->arena = $arena;

		$tile = $tilePosition->getLevel()->getTile($tilePosition);
		if(!($tile instanceof Sign)){
			throw new \RuntimeException("The position given are not a valid sign");
		}

		$this->signTile = $tile;
	}

	public function setAllText(array $text){
		$this->updatedSign = $text;
	}

	public function setLine(int $line = 0, string $text = ""){
		$this->updatedSign[$line] = $text;
	}

	public function onInteract(PlayerInteractEvent $e){
		$b = $e->getBlock();
		$p = $e->getPlayer();
		$pm = $this->arena->getPlayerManager();

		// Improved queue method.
		if($b->equals($this->signTile) && !$pm->inQueue($p)){
			if($this->arena->hasFlags(Arena::ARENA_CRASHED)){
				$p->sendMessage(TextFormat::RED . "The arena has crashed! Ask server owner to check server logs.");

				return;
			}

			$pm->addQueue($p);

			if($this->arena->hasFlags(Arena::ARENA_OFFLINE_MODE)){
				$p->sendMessage(TextFormat::GOLD . "Please wait while the arena is loading");
			}
		}
	}

	public function processSign(){
		foreach($this->updatedSign as $line => $text){
			if(($this->cache[$line] ?? "") !== $text){
				$this->signTile->setLine($line, $text);

				$this->cache[$line] = $text;
			}
		}
	}
}