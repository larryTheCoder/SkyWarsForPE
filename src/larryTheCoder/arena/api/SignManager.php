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
use larryTheCoder\arena\api\translation\TranslationContainer;
use pocketmine\block\StainedGlass;
use pocketmine\event\block\BlockBreakEvent;
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

	/** @var Position */
	private $signPosition;
	/** @var Arena */
	private $arena;
	/** @var string */
	private $prefix;

	/** @var string[] */
	private $cache = [];
	/** @var string[] */
	private $updatedSign = [];

	/** @var string[] */
	private $signTemplate = [];
	/** @var int[] */
	private $delay = [];

	public function __construct(Arena $arena, Position $tilePosition, string $prefix = ""){
		$this->arena = $arena;
		$this->prefix = $prefix;


		$this->signPosition = $tilePosition;
	}

	private static function toReadable(Arena $arena): string{
		switch(true){
			case $arena->hasFlags(Arena::ARENA_IN_SETUP_MODE):
				return TextFormat::ESCAPE . "eIn setup";
			case $arena->hasFlags(Arena::ARENA_DISABLED):
				return TextFormat::ESCAPE . "cDisabled";
			case $arena->hasFlags(Arena::ARENA_CRASHED):
				return TextFormat::ESCAPE . "cCrashed";
			case $arena->getStatus() === ArenaState::STATE_WAITING:
				return TextFormat::ESCAPE . "6Click to join!";
			case $arena->getStatus() === ArenaState::STATE_STARTING:
				return TextFormat::ESCAPE . "6Starting";
			case $arena->getStatus() === ArenaState::STATE_ARENA_RUNNING:
				return TextFormat::ESCAPE . "cRunning";
			case $arena->getStatus() === ArenaState::STATE_ARENA_CELEBRATING:
				return TextFormat::ESCAPE . "cEnded";
		}

		return TextFormat::ESCAPE . "eUnknown";
	}

	/**
	 * @param string[] $template
	 */
	public function setTemplate(array $template): void{
		$this->signTemplate = $template;
	}

	/**
	 * @param string[] $text
	 */
	public function setAllText(array $text): void{
		$this->updatedSign = $text;
	}

	public function setLine(int $line = 0, string $text = ""): void{
		$this->updatedSign[$line] = $text;
	}

	public function onInteract(PlayerInteractEvent $e): void{
		$b = $e->getBlock();
		$p = $e->getPlayer();

		$qm = $this->arena->getQueueManager();

		if($this->signPosition === null || !$b->equals($this->signPosition)){
			return;
		}

		$e->setCancelled();

		// Improved queue method.
		if(!$qm->inQueue($p) && $e->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK
			&& ($this->delay[$p->getName()] ?? 0) !== time()){

			$this->delay[$p->getName()] = time();

			if($this->arena->hasFlags(Arena::ARENA_CRASHED)){
				$p->sendMessage(TranslationContainer::getTranslation($p, 'arena-crashed'));

				return;
			}

			if($this->arena->hasFlags(Arena::ARENA_DISABLED) || $this->arena->hasFlags(Arena::ARENA_IN_SETUP_MODE)){
				$p->sendMessage(TranslationContainer::getTranslation($p, 'arena-disabled'));

				return;
			}

			$qm->addQueue($p);
			$p->sendMessage(TranslationContainer::getTranslation($p, 'arena-in-queue', [
				"{ARENA_NAME}" => $this->arena->getMapName(),
			]));
		}
	}

	public function processSign(): void{
		// Do not perform anything if the tile is null

		$signTile = $this->signPosition->getLevel()->getTile($this->signPosition);

		if(!($signTile instanceof Sign)) return;

		if(!empty($this->signTemplate)){
			$names = ['%alive', '%status', '%max', '%min', '&', '%world', '%prefix', '%name'];
			$replace = [
				$this->arena->getPlayerManager()->getPlayersCount(),
				self::toReadable($this->arena),
				$this->arena->getMaxPlayer(),
				$this->arena->getMinPlayer(),
				TextFormat::ESCAPE,
				$this->arena->getLevelName(),
				$this->prefix,
				$this->arena->getMapName(),
			];

			foreach($this->signTemplate as $id => $message){
				$message = str_replace($names, $replace, $message);

				$this->setLine($id, $message);
			}
		}

		foreach($this->updatedSign as $line => $text){
			if(($this->cache[$line] ?? "") !== $text){
				$signTile->setLine($line, $text);

				$this->cache[$line] = $text;
			}
		}

		// Block statuses.
		$level = $this->signPosition->getLevel();

		$block = $this->getBlockStatus();
		$sign = $signTile->getBlock();
		$vec = $sign->getSide($sign->getDamage() ^ 0x01);
		if($level->getBlock($vec)->getId() === $block->getId() && $level->getBlock($vec)->getDamage() === $block->getDamage()){
			return;
		}

		$level->setBlock($vec, $block);
	}

	public function onBlockBreakEvent(BlockBreakEvent $event): void{
		$block = $event->getBlock();

		if($block->equals($this->signPosition)) $event->setCancelled();

		$signTile = $this->signPosition->getLevel()->getTile($this->signPosition);
		if(!($signTile instanceof Sign)) return;
		$sign = $signTile->getBlock();

		$vec = $sign->getSide($sign->getDamage() ^ 0x01);
		if($block->equals($vec)) $event->setCancelled();
	}

	private function getBlockStatus(): StainedGlass{
		$arena = $this->arena;
		if($arena->hasFlags(Arena::ARENA_DISABLED) || $arena->hasFlags(Arena::ARENA_CRASHED) || $arena->hasFlags(Arena::ARENA_IN_SETUP_MODE)){
			return new StainedGlass(14);
		}elseif($arena->getStatus() === ArenaState::STATE_WAITING){
			return new StainedGlass(13);
		}elseif($arena->getStatus() === ArenaState::STATE_STARTING){
			return new StainedGlass(4);
		}elseif($arena->getStatus() === ArenaState::STATE_ARENA_RUNNING){
			return new StainedGlass(6);
		}elseif($arena->getStatus() === ArenaState::STATE_ARENA_CELEBRATING){
			return new StainedGlass(11);
		}else{
			return new StainedGlass(0);
		}
	}

	public function shutdown(): void{
		HandlerList::unregisterAll($this);
	}
}