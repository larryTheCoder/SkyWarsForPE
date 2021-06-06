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
use larryTheCoder\arena\api\PlayerManager;
use larryTheCoder\arena\api\scoreboard\ScoreFilter;
use larryTheCoder\arena\api\SignManager;
use larryTheCoder\arena\api\task\ArenaTickTask;
use larryTheCoder\arena\task\SkyWarsTask;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\cage\CageManager as CageHandler;
use larryTheCoder\utils\ConfigManager;
use larryTheCoder\utils\LootGenerator;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\block\BlockFactory;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\tile\Chest;

class ArenaImpl extends ArenaData {

	/** @var LootGenerator|null */
	public static $lootTables = null;

	// Allow invincible period on this arena.
	const ARENA_INVINCIBLE_PERIOD = 0x12;

	/** @var EventListener */
	private $eventListener;
	/** @var array */
	private $arenaData;
	/** @var SignManager */
	private $signManager;
	/** @var ConfigManager */
	private $configManager;

	/** @var Position[][] */
	private $toRemove = [];
	/** @var string[] */
	private $originalNametag = [];
	/** @var true[] */
	private $openedChests = [];

	/** @var int */
	public $startedTime = -1;

	/**
	 * ArenaImpl constructor.
	 * @param SkyWarsPE $plugin
	 * @param ConfigManager $arenaData
	 */
	public function __construct(SkyWarsPE $plugin, ConfigManager $arenaData){
		$this->setConfig($arenaData);

		$this->eventListener = new EventListener($this);

		parent::__construct($plugin);
	}

	/**
	 * @param ConfigManager $arenaData
	 * @param bool $duringRuntime
	 */
	public function setConfig(ConfigManager $arenaData, bool $duringRuntime = false): void{
		$this->configManager = $arenaData;

		$this->arenaData = $arenaData->getConfig()->getAll();

		$this->parseData();

		$this->setFlags(Arena::ARENA_DISABLED, !$this->arenaEnable);

		$this->signManager = new SignManager($this, $this->getSignPosition(), Settings::$prefix);
		$this->cageManager = new CageManager($this->spawnPedestals);
		$this->scoreboard = new ScoreFilter($this, Utils::getScoreboardConfig($this));

		$this->signManager->setTemplate([$this->statusLine1, $this->statusLine2, $this->statusLine3, $this->statusLine4]);

		if($duringRuntime){
			$this->shutdown();

			// Reinitialize the arena load sequence.
			parent::__construct($this->getPlugin());
		}
	}

	/**
	 * @return array<mixed>
	 */
	public function getArenaData(): array{
		return $this->arenaData;
	}

	public function getConfigManager(): ConfigManager{
		return $this->configManager;
	}

	public function getCodeName(): string{
		return "Seven Red Suns";
	}

	public function startArena(): void{
		$pm = $this->getPlayerManager();
		$kitManager = SkyWarsPE::getInstance()->getKitManager();

		foreach($pm->getAlivePlayers() as $player){
			// Set the player gamemode first
			$player->setGamemode(Player::SURVIVAL);
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

			if($kitManager !== null){
				$kitManager->claimKit($player);
			}
		}

		// Cage factory reset.
		// It is quite impossible for this to return an unloaded object since the
		// arena world is already loaded and the match has started.
		if($this->getLevel() !== null && !$this->getLevel()->isClosed()){
			foreach($this->toRemove as $data){
				foreach($data as $pos){
					$this->getLevel()->setBlock($pos, BlockFactory::get(0));
				}
			}
		}

		$this->toRemove = [];

		$this->startedTime = time();

		if($this->arenaGraceTime > 0){
			$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, true);
		}
	}

	public function getTimeStarted(): int{
		return $this->startedTime;
	}

	public function joinToArena(Player $player): void{
		parent::joinToArena($player);

		$player->setGamemode(Player::ADVENTURE);

		// Build up the cage object.
		$cage = CageHandler::getInstance()->getPlayerCage($player);
		$spawnLoc = $this->getCageManager()->getCage($player);

		$this->toRemove[$player->getName()] = $cage->build(Position::fromObject($spawnLoc, $this->getLevel()));

		$pm = $this->getPlayerManager();

		if($pm->teamMode){
			$this->originalNametag[$player->getName()] = $player->getNameTag();

			$player->setNameTag(PlayerManager::getColorByMeta($pm->getTeamColorRaw($player)) . $player->getName());
		}

		$this->getPlayerManager()->broadcastToPlayers('arena-join', false, [
			"{PLAYER}"        => $pm->getOriginName($player->getName(), $player->getName()),
			"{TOTAL_PLAYERS}" => count($pm->getAlivePlayers()),
			"{MAX_SIZE}"      => $this->maximumPlayers,
		]);

		if($this->getPlugin()->getKitManager() !== null){
			$player->getInventory()->setItem(0, self::getKitSelector());
		}
	}

	public function stopArena(): void{
		$this->startedTime = -1;

		$this->eventListener->resetEntry();

		$this->setFlags(self::ARENA_INVINCIBLE_PERIOD, false);
	}

	public function setSpectator(Player $player): void{
		parent::setSpectator($player);

		$player->setHealth(20);
		$player->setFood(20);

		$player->teleport(Position::fromObject($this->arenaSpecPos, $this->getLevel()));
	}

	public function unsetPlayer(Player $player, bool $isSpectator = false): void{
		if($isSpectator){
			$player->setAllowFlight(false);

			foreach(Server::getInstance()->getOnlinePlayers() as $pl){
				if(!$pl->canSee($player)){
					$pl->showPlayer($player);
				}
			}
		}elseif($this->startedTime !== -1){
			SkyWarsDatabase::addPlayedSince($player->getName(), time() - $this->startedTime);
		}

		if(!$player->isClosed()){
			$player->setGamemode(Settings::$defaultGamemode);

			$player->getInventory()->clearAll();
			$player->getArmorInventory()->clearAll();

			if($this->getPlayerManager()->teamMode && isset($this->originalNametag[$player->getName()])){
				$player->setNameTag($this->originalNametag[$player->getName()]);
				unset($this->originalNametag[$player->getName()]);
			}

			$player->setHealth(20);
			$player->setFood(20);
		}
	}

	public function leaveArena(Player $player, bool $onQuit = false): void{
		$pm = $this->getPlayerManager();

		if($onQuit){
			$pm->broadcastToPlayers('message-disconnected', false, ["{PLAYER}" => $player->getName()]);
		}else{
			$pm->broadcastToPlayers('message-left', false, ["{PLAYER}" => $player->getName()]);

			$player->teleport(SkyWarsDatabase::getLobby());
		}

		if(isset($this->toRemove[$player->getName()])){
			if($this->getLevel() !== null && !$this->getLevel()->isClosed()){
				foreach($this->toRemove[$player->getName()] as $pos){
					$this->getLevel()->setBlock($pos, BlockFactory::get(0));
				}
			}

			unset($this->toRemove[$player->getName()]);
		}

		parent::leaveArena($player, $onQuit);
	}

	/**
	 * @param Player $player
	 */
	public function onKitSelection(Player $player): void{
		$kitManager = SkyWarsPE::getInstance()->getKitManager();

		if($kitManager !== null){
			$kitManager->sendKits($player);
		}
	}

	/**
	 * @param Player $player
	 */
	public function onSpectatorSelection(Player $player): void{
		$this->getPlugin()->getPanel()->showSpectatorPanel($player, $this);
	}

	public function onRejoinSelection(Player $player): void{
		$player->chat("/sw leave");
		$player->chat("/sw random");
	}

	public function isChestRefilled(Chest $chest): bool{
		return isset($this->openedChests[Level::blockHash($chest->getFloorX(), $chest->getFloorY(), $chest->getFloorZ())]);
	}

	public function refillChest(Chest $chest): void{
		if($this->isChestRefilled($chest)){
			return;
		}

		$chest->getInventory()->clearAll();
		$chest->getInventory()->setContents(LootGenerator::getLoot());

		$this->openedChests[Level::blockHash($chest->getFloorX(), $chest->getFloorY(), $chest->getFloorZ())] = true;
	}

	public function refillChests(): void{
		$this->openedChests = [];
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
	public function getPlugin(): Plugin{
		/** @var SkyWarsPE $plugin */
		$plugin = parent::getPlugin();

		return $plugin;
	}
}