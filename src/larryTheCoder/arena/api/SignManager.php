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

use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\impl\ShutdownSequence;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\HandlerList;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

/**
 * Manages sign for a new changes.
 */
class SignManager implements Listener, ShutdownSequence {

	/** @var Sign */
	private $signTile;
	/** @var Arena */
	private $arena;

	/** @var string[] */
	private $cache = [];
	/** @var string[] */
	private $updatedSign = [];

	/** @var string[] */
	private $signTemplate = [];

	public function __construct(Arena $arena, Position $tilePosition){
		$this->arena = $arena;

		$tile = $tilePosition->getLevel()->getTile($tilePosition);
		if(!($tile instanceof Sign)){
			throw new \RuntimeException("The position given are not a valid sign");
		}

		$this->signTile = $tile;
	}

	private static function toReadable(Arena $arena){
		switch(true){
			case $arena->hasFlags(Arena::ARENA_IN_SETUP_MODE):
				return TextFormat::ESCAPE . "eIn setup";
			case $arena->hasFlags(Arena::ARENA_DISABLED):
				return TextFormat::ESCAPE . "cDisabled";
			case $arena->hasFlags(Arena::ARENA_CRASHED):
				return TextFormat::ESCAPE . "cCrashed";
			case $arena->getStatus() <= ArenaState::STATE_WAITING:
				return TextFormat::ESCAPE . "6Click to join!";
			case $arena->getStatus() >= ArenaState::STATE_STARTING:
				return TextFormat::ESCAPE . "6Starting";
			case $arena->getStatus() === ArenaState::STATE_ARENA_RUNNING:
				return TextFormat::ESCAPE . "cRunning";
			case $arena->getStatus() === ArenaState::STATE_ARENA_CELEBRATING:
				return TextFormat::ESCAPE . "cEnded";
		}

		return TextFormat::ESCAPE . "eUnknown";
	}

	public function setTemplate(array $template){
		$this->signTemplate = $template;
	}

	public function setAllText(array $text){
		$this->updatedSign = $text;
	}

	public function setLine(int $line = 0, string $text = ""){
		$this->updatedSign[$line] = $text;
	}

	/** @var string[] */
	private $delay = [];

	public function onInteract(PlayerInteractEvent $e){
		$b = $e->getBlock();
		$p = $e->getPlayer();
		$pm = $this->arena->getPlayerManager();

		// Improved queue method.
		if($b->equals($this->signTile) && !$pm->inQueue($p) && $e->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK
			&& ($this->delay[$p->getName()] ?? 0) !== time()){
			$this->delay[$p->getName()] = time();

			if($this->arena->hasFlags(Arena::ARENA_CRASHED)){
				$p->sendMessage(TextFormat::RED . "The arena has crashed! Ask server owner to check server logs.");

				return;
			}

			if($this->arena->hasFlags(Arena::ARENA_DISABLED)){
				$p->sendMessage(TextFormat::RED . "Arena is disabled");

				return;
			}

			$pm->addQueue($p);
			$p->sendMessage(TextFormat::GOLD . "You are now queuing for the arena, please wait.");
		}
	}

	public function processSign(){
		if(!empty($this->signTemplate)){
			$names = ['%alive', '%status', '%max', '%min', '&', '%world', '%prefix', '%name'];
			$replace = [
				$this->arena->getPlayerManager()->getPlayersCount(),
				self::toReadable($this->arena),
				$this->arena->getMaxPlayer(),
				$this->arena->getMinPlayer(),
				TextFormat::ESCAPE,
				$this->arena->getLevelName(),
				SkyWarsPE::getInstance()->getPrefix(),
				$this->arena->getMapName(),
			];

			foreach($this->signTemplate as $id => $message){
				$message = str_replace($names, $replace, $message);

				$this->setLine($id, $message);
			}
		}

		foreach($this->updatedSign as $line => $text){
			if(($this->cache[$line] ?? "") !== $text){
				$this->signTile->setLine($line, $text);

				$this->cache[$line] = $text;
			}
		}
	}

	public function shutdown(): void{
		HandlerList::unregisterAll($this);
	}
}