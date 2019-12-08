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

namespace larryTheCoder\arena\api;

use larryTheCoder\arena\api\listener\BasicListener;
use larryTheCoder\arena\Arena;
use larryTheCoder\arena\State;
use larryTheCoder\arena\tasks\ArenaGameTick;
use larryTheCoder\arena\tasks\SignTickTask;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;

/**
 * The runtime handler of the SW game itself. This class handles player
 * actions and controls the arena acts.
 *
 * @package larryTheCoder\arena\api
 */
class DefaultGameAPI extends GameAPI {

	/** @var SkyWarsPE */
	public $plugin;
	/** @var int */
	public $fallTime;
	/** @var int[] */
	public $kills;

	/** @var Position[] */
	private $cageToRemove;

	public function __construct(Arena $arena){
		parent::__construct($arena);

		$this->fallTime = $arena->arenaGraceTime;
		$this->plugin = SkyWarsPE::getInstance();
		Server::getInstance()->getPluginManager()->registerEvents(new BasicListener($this), $this->plugin);
	}

	public function joinToArena(Player $p): bool{
		# Set the player gamemode first
		$p->setGamemode(0);
		$p->getInventory()->clearAll();
		$p->getArmorInventory()->clearAll();

		# Set the player health and food
		$p->setMaxHealth(Settings::$joinHealth);
		$p->setMaxHealth($p->getMaxHealth());
		# just to be really sure
		if($p->getAttributeMap() != null){
			$p->setHealth(Settings::$joinHealth);
			$p->setFood(20);
		}

		# Then we save the data
		$this->kills[strtolower($p->getName())] = 0;

		# Okay saved then we get the spawn for the player
		$spawn = $this->arena->usedPedestals[$p->getName()][0];

		# Get the custom cages
		$cageLib = $this->plugin->getCage();
		$cage = $cageLib->getPlayerCage($p);
		$this->cageToRemove[strtolower($p->getName())] = $cage->build(Position::fromObject($spawn, $this->arena->getLevel()));

		$p->sendMessage(str_replace("{PLAYER}", $p->getName(), $this->plugin->getMsg($p, 'player-join')));

		return true;
	}

	public function leaveArena(Player $p, bool $force = false): bool{
		if($this->arena->getPlayerState($p) === State::PLAYER_ALIVE){
			if($this->arena->getStatus() !== State::STATE_ARENA_RUNNING || $force){
				if($force){
					$this->arena->messageArenaPlayers('leave-others', true, ["%1", "%2"], [$p->getName(), $this->arena->getPlayersCount()]);
				}
				$this->arena->checkAlive();
				$this->removeCage($p);
			}else{
				$p->sendMessage($this->plugin->getMsg($p, 'arena-running'));

				return false;
			}
		}
		if(!$force) $p->sendMessage($this->plugin->getMsg($p, 'player-leave-2'));

		# Reset the XP Level
		$p->setXpLevel(0);
		$p->removeAllEffects();
		$p->setGamemode(0);
		$p->getInventory()->clearAll();
		$p->getArmorInventory()->clearAll();

		Utils::sendDebug("leaveArena() is being called");
		Utils::sendDebug("User " . $p->getName() . " is leaving the arena.");

		return true;
	}

	/**
	 * Remove cage of the player
	 *
	 * @param Player $p
	 * @return bool
	 */
	public function removeCage(Player $p): bool{
		if(!isset($this->cageToRemove[strtolower($p->getName())])){
			return false;
		}
		foreach($this->cageToRemove[strtolower($p->getName())] as $pos){
			$this->arena->getLevel()->setBlock($pos, Block::get(0));
		}
		unset($this->cageToRemove[strtolower($p->getName())]);

		return true;
	}

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1 seconds
	 *
	 * @return array
	 */
	public function getRuntimeTasks(): array{
		return [new ArenaGameTick($this->arena, $this), new SignTickTask($this->arena)];
	}

	/**
	 * Do something when the code is trying to remove every players
	 * from the list.
	 */
	public function removeAllPlayers(){
		// TODO: Implement removeAllPlayers() method.
	}

	/**
	 * Start the arena, begin the match in the
	 * arena provided.
	 */
	public function startArena(): void{
		// TODO: Implement startArena() method.
	}

	/**
	 * Stop the arena, rollback to defaults and
	 * reset the arena if possible.
	 */
	public function stopArena(): void{
		// TODO: Implement stopArena() method.
	}

	public function giveGameItems(Player $p, bool $true){
		// TODO
	}
}