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
use larryTheCoder\arena\api\scoreboard\Internal;
use larryTheCoder\arena\api\SignManager;
use larryTheCoder\arena\api\task\ArenaTickTask;
use larryTheCoder\arena\task\SkyWarsTask;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\block\BlockFactory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\tile\Chest;

class ArenaImpl extends ArenaData {

	// Allow invincible period on this arena.
	const ARENA_INVINCIBLE_PERIOD = 0x12;

	/** @var EventListener */
	private $eventListener;
	/** @var array<mixed> */
	private $arenaData;
	/** @var SignManager */
	private $signManager;
	/** @var float */
	private $startedTime = -1;

	/** @var Position[][] */
	private $toRemove = [];

	/**
	 * ArenaImpl constructor.
	 * @param SkyWarsPE $plugin
	 * @param array<mixed> $arenaData
	 */
	public function __construct(SkyWarsPE $plugin, array $arenaData){
		$this->setConfig($arenaData);

		$this->eventListener = new EventListener($this);
		$this->signManager = new SignManager($this, $this->getSignPosition(), Settings::$prefix);
		$this->cageManager = new CageManager($this->spawnPedestals);
		$this->scoreboard = new Internal($this, Utils::getScoreboardConfig());

		$this->signManager->setTemplate([$this->statusLine1, $this->statusLine2, $this->statusLine3, $this->statusLine4]);

		parent::__construct($plugin);
	}

	/**
	 * @param array<mixed> $arenaData
	 */
	public function setConfig(array $arenaData): void{
		$this->arenaData = $arenaData;

		$this->parseData();

		$this->setFlags(Arena::ARENA_DISABLED, !$this->arenaEnable);

		$this->signManager = new SignManager($this, $this->getSignPosition(), Settings::$prefix);
		$this->cageManager = new CageManager($this->spawnPedestals);
		$this->scoreboard = new Internal($this, Utils::getScoreboardConfig());

		$this->signManager->setTemplate([$this->statusLine1, $this->statusLine2, $this->statusLine3, $this->statusLine4]);
	}

	/**
	 * @return array<mixed>
	 */
	public function getArenaData(): array{
		return $this->arenaData;
	}

	public function getCodeName(): string{
		return "Seven Red Suns";
	}

	public function startArena(): void{
		$pm = $this->getPlayerManager();

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
		}

		// Cage factory reset.
		foreach($this->toRemove as $data){
			foreach($data as $pos){
				$this->getLevel()->setBlock($pos, BlockFactory::get(0));
			}
		}

		$this->toRemove = [];

		$this->startedTime = microtime(true);

		$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, true);
	}

	public function getTimeStarted(): float{
		return $this->startedTime;
	}

	public function joinToArena(Player $player): void{
		parent::joinToArena($player);

		$player->setGamemode(Player::ADVENTURE);

		// Build up the cage object.
		$cage = $this->getPlugin()->getCage()->getPlayerCage($player);
		$spawnLoc = $this->getCageManager()->getCage($player);

		$this->toRemove[$player->getName()] = $cage->build(Position::fromObject($spawnLoc, $this->getLevel()));
	}

	public function stopArena(): void{
		// TODO: Spawn to default lobby location.
		foreach($this->getPlayerManager()->getAllPlayers() as $player){
			$player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
		}

		$this->startedTime = -1;

		$this->eventListener->resetEntry();

		$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, false);
	}

	public function setSpectator(Player $player): void{
		parent::setSpectator($player);

		$player->setHealth(20);
		$player->setFood(20);

		$player->setGamemode(Player::ADVENTURE);
		$player->setAllowFlight(true);
		$player->sendMessage(SkyWarsPE::getInstance()->getMsg($player, 'player-spectate'));

		$player->teleport(Position::fromObject($this->arenaSpecPos, $this->getLevel()));
	}

	public function unsetPlayer(Player $player, bool $isSpectator = false): void{
		$player->setGamemode(0);

		if($isSpectator){
			$player->setAllowFlight(false);

			foreach(Server::getInstance()->getOnlinePlayers() as $pl){
				if(!$pl->canSee($player)){
					$pl->showPlayer($player);
				}
			}
		}else{
			$player->getInventory()->clearAll();
			$player->getArmorInventory()->clearAll();
		}

		$player->setHealth(20);
		$player->setFood(20);
	}

	public function leaveArena(Player $player, bool $onQuit = false): void{
		$pm = $this->getPlayerManager();

		if($onQuit){
			$pm->broadcastToPlayers("{$player->getName()} has disconnected.");
		}else{
			$pm->broadcastToPlayers("{$player->getName()} has left the game.");

			$player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
		}

		if(isset($this->toRemove[$player->getName()])){
			foreach($this->toRemove[$player->getName()] as $pos){
				$this->getLevel()->setBlock($pos, BlockFactory::get(0));
			}

			unset($this->toRemove[$player->getName()]);
		}

		parent::leaveArena($player, $onQuit);
	}

	public function refillChests(): void{
		$contents = Utils::getChestContents();
		foreach($this->getLevel()->getTiles() as $tile){
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

	public function getMinPlayer(): int{
		return $this->minimumPlayers;
	}

	public function getMaxPlayer(): int{
		return $this->maximumPlayers;
	}

	public function getMapName(): string{
		return $this->arenaFileName;
	}

	public function getLevelName(): string{
		return $this->arenaWorld;
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

	/**
	 * @return SkyWarsPE
	 */
	public function getPlugin(): Plugin
	{
		/** @var SkyWarsPE $plugin */
		$plugin = parent::getPlugin();

		return $plugin;
	}
}