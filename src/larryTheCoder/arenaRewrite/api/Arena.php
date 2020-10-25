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

declare(strict_types = 1);

namespace larryTheCoder\arenaRewrite\api;

use larryTheCoder\arenaRewrite\api\impl\ArenaListener;
use larryTheCoder\arenaRewrite\api\impl\ArenaState;
use larryTheCoder\arenaRewrite\api\impl\ShutdownSequence;
use larryTheCoder\arenaRewrite\api\task\ArenaTickTask;
use larryTheCoder\arenaRewrite\api\task\AsyncDirectoryDelete;
use larryTheCoder\arenaRewrite\api\task\CompressionAsyncTask;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

/**
 * Arena interface, here you will be able to control the arena behaviour and attach them into pre-defined
 * arena data, explicitly, the given function are the skeleton for every operation in a gamemode.
 * <p>
 * This is a very basic arena listener.
 *
 * @package larryTheCoder\arenaRewrite\api
 */
abstract class Arena implements ShutdownSequence {

	// Arena flag constants, you can apply your own constants
	// for arena flags in setFlags() and hasFlags()
	public const WORLD_ATTEMPT_LOAD = 0x1;
	public const ARENA_OFFLINE_MODE = 0x2;
	/** @var CageManager */
	protected $cageManager;
	/** @var string|null */
	protected $lobbyName = null;
	/** @var string */
	protected $arenaName;
	/** @var int */
	private $arenaStatus = ArenaState::STATE_WAITING;
	/** @var PlayerManager */
	private $playerData;
	/** @var Level|null */
	private $lobbyLevel = null;
	/** @var Level|null */
	private $level = null;
	/** @var Plugin */
	private $plugin;
	/** @var int */
	private $deleteTimeout = 0;
	/** @var int */
	private $gameFlags = 0x0;

	public function __construct(Plugin $plugin){
		$this->plugin = $plugin;
		$this->playerData = new PlayerManager($this);

		$plugin->getScheduler()->scheduleRepeatingTask($this->getArenaTask(), 20);
	}

	public abstract function getArenaTask(): ArenaTickTask;

	/**
	 * The API codename.
	 *
	 * @return string
	 */
	public abstract function getCodeName(): string;

	/**
	 * Start the arena, begin the match in the
	 * arena provided.
	 */
	public abstract function startArena(): void;

	/**
	 * Stop the arena, rollback to defaults and
	 * reset the arena if possible.
	 */
	public abstract function stopArena(): void;

	/**
	 * Shutdown this API from using this arena.
	 * You may find this a very useful function.
	 */
	public abstract function shutdown(): void;

	public abstract function getMinPlayer(): int;

	public abstract function getEventListener(): ArenaListener;

	public abstract function getSignManager(): SignManager;

	/**
	 * Called when a player leaves the arena.
	 *
	 * @param Player $player
	 * @param bool $force
	 */
	public function leaveArena(Player $player, bool $force = false): void{
		$this->unsetPlayer($player);

		$this->getPlayerManager()->removePlayer($player);
		$this->getCageManager()->removeCage($player);
	}

	/**
	 * Reset the player objects that were set in game.
	 *
	 * @param Player $player
	 */
	public abstract function unsetPlayer(Player $player);

	/**
	 * Return the object where the players are being queued into
	 * the arena, this storage unit stores player and spectators.
	 *
	 * @return PlayerManager
	 */
	public function getPlayerManager(): PlayerManager{
		return $this->playerData;
	}

	public function getCageManager(): CageManager{
		return $this->cageManager;
	}

	/**
	 * Attempt to process queue of a player.
	 */
	public function processQueue(): void{
		// Wait until the async task has successfully deletes it file, then we can add more queue.
		if($this->deleteTimeout >= 30){
			return;
		}

		$pm = $this->getPlayerManager();
		$queue = $pm->getQueue();

		// Attempt to load the level while the level is offline.
		if($this->hasFlags(self::ARENA_OFFLINE_MODE) && !$this->hasFlags(self::WORLD_ATTEMPT_LOAD)){
			$this->loadWorld();

			return;
		}

		// Process queue for players attempting to join as a "contestant" when the arena is not running and
		// the player alive size is below max player.
		if(!($this->getStatus() === ArenaState::STATE_WAITING || $this->getStatus() === ArenaState::STATE_STARTING)){
			foreach($queue as $player){
				if(count($pm->getAlivePlayers()) < $this->getMaxPlayer()){
					$pm->addPlayer($player);

					$this->joinToArena($player);
				}else{
					break;
				}
			}
		}

		// Otherwise use up the leftover queue for spectators
		if(!empty($queue)){
			foreach($queue as $player){
				$pm->setSpectator($player);
				$this->playerSpectate($player);
			}
		}

		// Attempt to reset the world when there is no players in the arena,
		// this is to avoid unnecessary ticks on this world.
		if(!$this->hasFlags(self::ARENA_OFFLINE_MODE) && count($pm->getAlivePlayers()) === 0){
			if($this->deleteTimeout >= 30 && !$this->level->isClosed()){
				Server::getInstance()->unloadLevel($this->level, true);

				$loadedLevel = [];
				if($this->lobbyName !== null){
					$loadedLevel[] = $this->lobbyName;
				}
				$loadedLevel[] = $this->level->getFolderName();

				$task = new AsyncDirectoryDelete($loadedLevel, function(){
					$this->setFlags(self::ARENA_OFFLINE_MODE, true);

					$this->deleteTimeout = 0;
				});
				Server::getInstance()->getAsyncPool()->submitTask($task);
			}

			$this->deleteTimeout++;
		}else{
			$this->deleteTimeout = 0;
		}
	}

	public function hasFlags(int $flagId): bool{
		return (($this->gameFlags >> $flagId) & 1) === 1;
	}

	final public function loadWorld(bool $onStart = true){
		// Lobby/Arena pre loading.
		if($onStart){
			if($this->lobbyName === null){
				$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $this->arenaName . ".zip";
				$toPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $this->arenaName;
			}else{
				$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $this->lobbyName . ".zip";
				$toPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $this->lobbyName;
			}
		}else{
			if($this->lobbyName === null) return; // Do nothing because the level has already been loaded.

			$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $this->arenaName . ".zip";
			$toPath = $this->plugin->getServer()->getDataPath() . "worlds/" . $this->arenaName;
		}

		if(is_file($this->plugin->getDataFolder() . 'arenas/worlds/')) return;
		if(!file_exists($toPath)) @mkdir($toPath, 0755);

		$task = new CompressionAsyncTask([$fromPath, $toPath, false], function() use ($onStart){
			Server::getInstance()->loadLevel($this->arenaName);

			if($this->lobbyName !== null && $onStart){
				$level = $this->lobbyLevel = Server::getInstance()->getLevelByName($this->arenaName);
				$this->lobbyLevel->setAutoSave(false);

				$isLobby = true;
			}else{
				$level = $this->level = Server::getInstance()->getLevelByName($this->arenaName);
				$this->level->setAutoSave(false);

				$isLobby = false;
			}

			$this->setFlags(self::ARENA_OFFLINE_MODE, false);
			$this->setFlags(self::WORLD_ATTEMPT_LOAD, false);

			$this->initArena($level, $isLobby);
		});

		Server::getInstance()->getAsyncPool()->submitTask($task);

		$this->setFlags(self::WORLD_ATTEMPT_LOAD, true);
	}

	public function setFlags(int $flagId, bool $flags): void{
		if($flags){
			$this->gameFlags |= 1 << $flagId;
		}else{
			$this->gameFlags &= ~(1 << $flagId);
		}
	}

	/**
	 * Perform appropriate execution after the arena world has
	 * successfully being copied and loaded.
	 *
	 * @param Level $level
	 * @param bool $isLobby
	 */
	public abstract function initArena(Level $level, bool $isLobby): void;

	public function getStatus(): int{
		return $this->arenaStatus;
	}

	public abstract function getMaxPlayer(): int;

	/**
	 * Called when a player joins into the arena, this will only be called
	 * by a function and this function will be called when a player joined as
	 * a "contestant" or a "spectator".
	 *
	 * @param Player $player
	 */
	public function joinToArena(Player $player): void{
		$this->getPlayerManager()->addPlayer($player);

		$cage = $this->getCageManager()->setCage($player);
		if($this->lobbyName !== null){
			$player->teleport($this->level->getSafeSpawn());
		}else{
			$player->teleport(Position::fromObject($cage, $this->level));
		}
	}

	/**
	 * @param Player $player
	 */
	public abstract function playerSpectate(Player $player): void;

	final public function resetArena(): void{
		foreach($this->getPlayerManager()->resetPlayers() as $player){
			$this->unsetPlayer($player);
		}

		$this->setStatus(ArenaState::STATE_WAITING);
	}

	public function setStatus(int $status): void{
		$this->arenaStatus = $status;
	}

	/**
	 * Attempt to check for alive players in the arena.
	 */
	final public function checkAlive(){
		if($this->getStatus() !== ArenaState::STATE_ARENA_RUNNING) return;

		$pm = $this->getPlayerManager();
		if($pm->isSolo()){
			$playerCount = count($pm->getAlivePlayers());
		}else{
			$playerCount = count(array_unique($pm->getAliveTeam(), SORT_NUMERIC));
		}

		if($playerCount <= 1){
			$this->setStatus(ArenaState::STATE_ARENA_CELEBRATING);
		}
	}

	public function getPlugin(): Plugin{
		return $this->plugin;
	}
}