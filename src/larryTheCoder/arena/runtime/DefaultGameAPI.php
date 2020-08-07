<?php
/**
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

namespace larryTheCoder\arena\runtime;

use larryTheCoder\arena\api\ArenaAPI;
use larryTheCoder\arena\api\ArenaListener;
use larryTheCoder\arena\api\ArenaState;
use larryTheCoder\arena\api\ArenaTask;
use larryTheCoder\arena\api\listener\BasicListener;
use larryTheCoder\arena\Arena;
use larryTheCoder\arena\runtime\listener\BaseListener;
use larryTheCoder\arena\runtime\tasks\ArenaGameTick;
use larryTheCoder\arena\runtime\tasks\SignTickTask;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\block\Block;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\HandlerList;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\level\sound\GenericSound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Chest;
use pocketmine\utils\TextFormat;

/**
 * The runtime handler of the SW game itself. This class handles player
 * actions and controls the arena acts.
 *
 * @package larryTheCoder\arena\api
 */
class DefaultGameAPI implements ArenaAPI {

	/** @var SkyWarsPE */
	public $plugin;
	/** @var int */
	public $fallTime = 0;
	/** @var array<int, <int,string>> */
	public $winners = [];
	/** @var int[] */
	public $winnersFixed = [];
	/** @var ArenaScoreboard */
	public $scoreboard;

	/** @var Position[][] */
	private $cageToRemove;

	/** @var Arena */
	public $arena;
	/** @var BasicListener */
	private $eventListener;

	public function __construct(Arena $arena){
		$this->arena = $arena;

		$this->fallTime = $arena->arenaGraceTime;
		$this->plugin = SkyWarsPE::getInstance();
		$this->scoreboard = new ArenaScoreboard($this);
		Server::getInstance()->getPluginManager()->registerEvents($this->eventListener = new BasicListener($this), $this->plugin);
	}

	public function getDebugger(): GameDebugger{
		return $this->arena->getDebugger();
	}

	public function joinToArena(Player $p, Vector3 $spawnPos): bool{
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

		$this->scoreboard->addPlayer($p);

		# Get the custom cages
		$cageLib = $this->plugin->getCage();
		$cage = $cageLib->getPlayerCage($p);
		$this->cageToRemove[$p->getName()] = $cage->build(Position::fromObject($spawnPos, $this->arena->getLevel()));

		$p->sendMessage(str_replace("{PLAYER}", $p->getName(), $this->plugin->getMsg($p, 'player-join')));

		return true;
	}

	public function leaveArena(Player $p, bool $force = false): bool{
		if($this->arena->getPlayerState($p) === ArenaState::PLAYER_ALIVE){
			if($this->arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING || $force){
				if($force){
					$this->arena->broadcastToPlayers('leave-others', false, ["%1", "%2"], [$p->getName(), $this->arena->getPlayersCount()]);
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
		$this->scoreboard->removePlayer($p);

		$this->getDebugger()->log("leaveArena() is being called");
		$this->getDebugger()->log("User " . $p->getName() . " is leaving the arena.");

		return true;
	}

	/**
	 * Remove cage of the player
	 *
	 * @param Player $p
	 * @return bool
	 */
	public function removeCage(Player $p): bool{
		if(!isset($this->cageToRemove[$p->getName()])){
			return false;
		}
		foreach($this->cageToRemove[$p->getName()] as $pos){
			$this->arena->getLevel()->setBlock($pos, Block::get(0));
		}
		unset($this->cageToRemove[$p->getName()]);

		return true;
	}

	/**
	 * Return the tasks required by the game to run.
	 * This task will be executed periodically for each 1 seconds
	 *
	 * @return ArenaTask[]
	 */
	public function getRuntimeTasks(): array{
		return [new ArenaGameTick($this->arena, $this), new SignTickTask($this->arena)];
	}

	public function statusUpdate(): void{
		$i = 0;
		arsort($this->arena->kills);
		foreach($this->arena->kills as $player => $kills){
			$this->winners[$i] = [$player, $kills];
			$this->winnersFixed[$player] = $i + 1;
			$i++;
		}

		$i = $this->arena->maximumPlayers - 1;
		while($i >= 0){
			if(!isset($this->winners[$i])){
				$this->winners[$i] = ["§7...", 0];
			}
			$i--;
		}
	}

	/**
	 * Do something when the code is trying to remove every players
	 * from the list.
	 */
	public function removeAllPlayers(): void{
		$this->cageToRemove = [];
	}

	/**
	 * Start the arena, begin the match in the
	 * arena provided.
	 */
	public function startArena(): void{
		foreach($this->arena->getPlayers() as $p){
			if($p instanceof Player){
				$p->setMaxHealth(Settings::$joinHealth);
				$p->setMaxHealth($p->getMaxHealth());
				$p->getInventory()->clear(0, true);
				$p->getInventory()->clear(8, true);
				$p->getArmorInventory()->clearAll();
				if($p->getAttributeMap() != null){//just to be really sure
					$p->setHealth(Settings::$joinHealth);
					$p->setFood(20);
				}

				$this->removeCage($p);

				$p->setXpLevel(0);
				$p->sendTitle($this->plugin->getMsg($p, "arena-game-started", false));
				$p->getLevel()->addSound(new GenericSound($p, LevelEventPacket::EVENT_SOUND_ORB, 3));

				Utils::addParticles($p->getLevel(), $p->getPosition()->add(0, -5, 0), 100);
			}
		}

		$this->scoreboard->setCurrentEvent(TextFormat::RED . "In match");

		$this->refillChests();
		$this->arena->broadcastToPlayers('arena-start', false);
	}

	/**
	 * Stop the arena, rollback to defaults and
	 * reset the arena if possible.
	 */
	public function stopArena(): void{
		$this->scoreboard->clearAll();

		$this->broadcastResult();
	}

	public function giveGameItems(Player $p, bool $true): void{
		// TODO
	}

	/**
	 * Refills chest that is available in this arena.
	 *
	 * TODO: Chest items can be configured in the config file.
	 */
	public function refillChests(): void{
		$contents = Utils::getChestContents();
		foreach($this->arena->getLevel()->getTiles() as $tile){
			if($tile instanceof Chest){
				//CLEARS CHESTS
				$tile->getInventory()->clearAll();
				//SET CONTENTS
				if(empty($contents)) $contents = Utils::getChestContents();
				foreach(array_shift($contents) as $key => $val){
					$item = Item::get($val[0], 0, $val[1]);
					if($item->getId() == Item::IRON_SWORD ||
						$item->getId() == Item::DIAMOND_SWORD){
						$enchantment = Enchantment::getEnchantment(Enchantment::SHARPNESS);
						$item->addEnchantment(new EnchantmentInstance($enchantment, mt_rand(1, 2)));
					}elseif($item->getId() == Item::LEATHER_TUNIC ||
						$item->getId() == Item::CHAIN_CHESTPLATE ||
						$item->getId() == Item::IRON_CHESTPLATE ||
						$item->getId() == Item::GOLD_CHESTPLATE ||
						$item->getId() == Item::DIAMOND_CHESTPLATE ||
						$item->getId() == Item::DIAMOND_LEGGINGS ||
						$item->getId() == Item::DIAMOND_HELMET){
						$enchantment = Enchantment::getEnchantment(Enchantment::PROTECTION);
						$item->addEnchantment(new EnchantmentInstance($enchantment, mt_rand(1, 2)));
					}elseif($item->getId() == Item::BOW){
						$enchantment = Enchantment::getEnchantment(Enchantment::POWER);
						$item->addEnchantment(new EnchantmentInstance($enchantment, mt_rand(1, 2)));
					}

					$tile->getInventory()->addItem($item);
				}
			}
		}
		unset($contents, $tile);
	}

	public function broadcastResult(): void{
		foreach($this->arena->getPlayers() as $p){
			$p->setXpLevel(0);
		}

		// Execute a command for each winners.
		foreach($this->winners as $slot => $player){
			if(!isset($this->arena->winnersCommand[$slot])){
				break;
			}

			$command = $this->arena->winnersCommand[$slot];
			if(!is_array($command)){
				Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $command);
			}else{
				foreach($command as $cmd){
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), $cmd);
				}
			}
		}
	}

	public function getCodeName(): string{
		return "SkyWars-BETA";
	}

	public function shutdown(): void{
		// Now this will work oop
		$this->scoreboard->clearAll();

		HandlerList::unregisterAll($this->eventListener);
	}

	public function getEventListener(): ArenaListener{
		return new BaseListener($this->arena);
	}
}