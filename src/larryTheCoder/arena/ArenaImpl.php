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

namespace larryTheCoder\arena;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\CageManager;
use larryTheCoder\arena\api\impl\ArenaListener;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\SignManager;
use larryTheCoder\arena\api\task\ArenaTickTask;
use larryTheCoder\arena\task\SkyWarsTask;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use pocketmine\block\BlockFactory;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;

class ArenaImpl extends Arena {
	use ArenaData;

	// Allow invincible period on this arena.
	const ARENA_INVINCIBLE_PERIOD = 0x4;

	/** @var EventListener */
	private $eventListener;
	/** @var array */
	private $arenaData;
	/** @var SignManager */
	private $signManager;
	/** @var float */
	private $startedTime = -1;

	public function __construct(SkyWarsPE $plugin, array $arenaData){
		$this->arenaData = $arenaData;
		$this->parseData();

		$this->eventListener = new EventListener($this);
		$this->signManager = new SignManager($this, $this->getSignPosition());
		$this->cageManager = new CageManager($this->spawnPedestals);

		parent::__construct($plugin);
	}

	public function setConfig(array $arenaData): void{
		$this->arenaMode = $arenaData;

		$this->parseData();
	}

	public function getArenaData(): array{
		return $this->arenaData;
	}

	public function getCodeName(): string{
		return "Seven Red Suns";
	}

	public function startArena(): void{
		$pm = $this->getPlayerManager();
		$cm = $this->getCageManager();

		foreach($pm->getAlivePlayers() as $player){
			// Set the player gamemode first
			$player->setGamemode(0);
			$player->getInventory()->clearAll();
			$player->getArmorInventory()->clearAll();

			// Set the player health and food
			$player->setMaxHealth(Settings::$joinHealth);
			$player->setMaxHealth($player->getMaxHealth());

			// just to be really sure
			if($player->getAttributeMap() != null){
				$player->setHealth(Settings::$joinHealth);
				$player->setFood(20);
			}

			// Cage factory reset.
			$pos = $cm->getCage($player);
			foreach($pos as $block){
				$this->getLevel()->setBlock(BlockFactory::get(0), $block);
			}
		}

		$this->startedTime = microtime(true);

		$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, true);
	}

	public function getTimeStarted(): float{
		return $this->startedTime;
	}

	public function joinToArena(Player $player): void{
		parent::joinToArena($player);

		$player->setGamemode(Player::ADVENTURE);
	}

	public function stopArena(): void{
		// TODO: Spawn to default lobby location.
		foreach($this->getPlayerManager()->getAllPlayers() as $player){
			$player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
		}

		$this->startedTime = -1;

		$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, false);
	}

	public function playerSpectate(Player $player): void{
		$pm = $this->getPlayerManager();

		$player->setGamemode(Player::ADVENTURE);
		$player->setAllowFlight(true);
		$player->sendMessage(SkyWarsPE::getInstance()->getMsg($player, 'player-spectate'));

		foreach($pm->getAllPlayers() as $p2) $p2->hidePlayer($player);

		$player->teleport(Position::fromObject($this->arenaSpecPos, $this->getLevel()));
	}

	public function unsetPlayer(Player $player, bool $isSpectator = false){
		$player->setGamemode(0);

		if($isSpectator){
			$player->setAllowFlight(false);
		}else{
			$player->getInventory()->clearAll();
			$player->getArmorInventory()->clearAll();
		}
	}

	public function leaveArena(Player $player, bool $force = false): void{
		$pm = $this->getPlayerManager();
		$isSpectator = $pm->isSpectator($player->getName());

		// Do nothing if the player itself is a spectator or the arena
		// is not running.
		if($isSpectator || $this->getStatus() !== ArenaState::STATE_ARENA_RUNNING) return;

		if($force){
			$pm->broadcastToPlayers("{$player->getName()} has disconnected.");
		}else{
			$pm->broadcastToPlayers("{$player->getName()} has left the game.");

			$player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
		}

		parent::leaveArena($player, $force);
	}

	public function getMinPlayer(): int{
		return $this->minimumPlayers;
	}

	public function getMaxPlayer(): int{
		return $this->maximumPlayers;
	}

	public function getMapName(): string{
		return $this->arenaName;
	}

	public function getMode(): int{
		return $this->arenaMode;
	}

	public function getEventListener(): ArenaListener{
		return $this->eventListener;
	}

	public function getSignManager(): SignManager{
		return $this->signManager;
	}

	public function getArenaTask(): ArenaTickTask{
		return new SkyWarsTask($this);
	}
}