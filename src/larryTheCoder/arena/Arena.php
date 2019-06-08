<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
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

namespace larryTheCoder\arena;

use http\Exception\InvalidArgumentException;
use larryTheCoder\events\PlayerJoinArenaEvent;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\{Settings, Utils};
use pocketmine\{item\enchantment\Enchantment, item\enchantment\EnchantmentInstance, Player, Server};
use pocketmine\block\{Block, StainedGlass};
use pocketmine\event\HandlerList;
use pocketmine\item\Item;
use pocketmine\level\{Level, Position};
use pocketmine\level\sound\{ClickSound, EndermanTeleportSound, GenericSound};
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\tile\Chest;
use pocketmine\utils\TextFormat;

/**
 * The Arena main class for SkyWars game
 *
 * @package larryTheCoder\arena
 */
class Arena extends PlayerHandler {

	const ARENA_SOLO = 0;
	const ARENA_TEAM = 1;

	const ARENA_WAITING_PLAYERS = 0;
	const ARENA_RUNNING = 1;
	const ARENA_CELEBRATING = 2;

	/** @var array */
	public $data;
	/** @var int */
	public $fallTime = 0;
	/** @var bool */
	public $setup = false;
	/** @var int */
	public $totalPlayed = 0;
	/** @var bool */
	public $disabled;
	/** @var SkyWarsPE */
	public $plugin;
	/** @var int[] */
	public $chestId = [];
	/** @var Level */
	protected $level;
	/** @var string */
	protected $id;
	/** @var null|ArenaSchedule */
	protected $task;
	/** @var ArenaListener */
	protected $listener;
	/** @var int */
	private $game = 0;

	public function __construct(string $id, SkyWarsPE $plugin){
		parent::__construct($this);
		$this->id = $id;
		$this->plugin = $plugin;
		$this->data = $plugin->getArenaManager()->getArenaConfig($id);
		$this->checkWorlds();
		if(!$this->reload()){
			$this->plugin->getLogger()->info($plugin->getPrefix() . TextFormat::RED . 'An error occurred while reloading the arena: ' . $id);

			return;
		}
		# Arena Listener
		if(!isset($this->task)){
			$this->plugin->getScheduler()->scheduleDelayedRepeatingTask($this->task = new ArenaSchedule($this), 20, 20);
		}
		try{
			$plugin->getServer()->getPluginManager()->registerEvents($this->listener = new ArenaListener($this->plugin, $this), $plugin);
		}catch(\Throwable $e){
		}
	}

	public function checkWorlds(){
		Utils::loadFirst($this->data['signs']['join_sign_world']);
		Utils::loadFirst($this->data['arena']['arena_world'], false); // Sometimes, the owner doesn't create the level, ish

		# Stop the world arena to ensure that the level is not
		# Changed when the arena is attempting to create a zip file
		$this->saveArenaWorld();
	}

	private function saveArenaWorld(){
		$levelName = $this->data['arena']['arena_world'];

		$fromPath = $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelName;
		$toPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName;

		// Reverted from diff 65e8fb78
		Utils::ensureDirectory($toPath);
		if(!Settings::$zipArchive){
			if(file_exists($toPath)){
				return;
			}

			// Just copy it.
			Utils::copyResourceTo($fromPath, $toPath);
		}else{
			if(is_file("$toPath.zip")){
				return;
			}

			// Get real path for our folder
			$rootPath = realpath($fromPath);

			// Initialize archive object
			$zip = new \ZipArchive();
			$zip->open("$toPath.zip", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

			// Create recursive directory iterator
			/** @var \SplFileInfo[] $files */
			$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($rootPath), \RecursiveIteratorIterator::LEAVES_ONLY);

			foreach($files as $name => $file){
				// Skip directories (they would be added automatically)
				if(!$file->isDir()){
					// Get real and relative path for current file
					$filePath = $file->getRealPath();
					$relativePath = substr($filePath, strlen($rootPath) + 1);

					// Add current file to archive
					$zip->addFile($filePath, $relativePath);
				}
			}

			// Zip archive will be created only after closing object
			$zip->close();
		}
	}

	public function reload(): bool{
		$levelName = $this->data['arena']['arena_world'];
		if($this->plugin->getServer()->isLevelLoaded($levelName)){
			$this->checkLevelTime();
			$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($levelName));
		}
		$this->fallTime = $this->data['arena']['grace_time'];

		$fromPath = $this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName;
		$toPath = $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelName;

		Utils::deleteDirectory($toPath);
		if(!Settings::$zipArchive){
			if(file_exists($toPath)){
				return false;
			}

			Utils::copyResourceTo($fromPath, $toPath);
		}else{
			if(!is_file("$fromPath.zip")){
				return false;
			}

			$zip = new \ZipArchive;
			if($zip->open("$fromPath.zip")){
				// Extract it to this path
				$zip->extractTo($toPath);
				$zip->close();
			}else{
				return false;
			}
		}

		return true;
	}

	public function checkLevelTime(){
		if(strtolower($this->data['arena']['time'] !== "false") && !is_null($this->level)){
			if(is_string($this->data['arena']['time'])){
				$this->level->setTime(str_replace(['day', 'night'], [6000, 18000], $this->data['arena']['time']));
			}else{
				$this->level->setTime($this->data['arena']['time']);
			}
			$this->level->stopTime();
			if($this->level->getAutoSave()){
				$this->level->setAutoSave(false);
			}
		}
	}

	/**
	 * Reset the world data
	 */
	public function reset(){
		$levelName = $this->data['arena']['arena_world'];
		if($this->plugin->getServer()->isLevelLoaded($levelName)){
			$this->plugin->getServer()->unloadLevel($this->level, true);
		}

		// This is the path to the backup world
		// We need to remove this directory/file.
		$backupWorld = $this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName;
		if(!Settings::$zipArchive){
			Utils::deleteDirectory($backupWorld);
		}else{
			unlink("$backupWorld.zip");
		}
		$this->saveArenaWorld();
	}

	public function recheckArena($i = 0){
		if($i >= 10){
			Server::getInstance()->getLogger()->error("FAILURE! WORLD {$this->data['arena']['arena_world']} COULD NOT BE LOADED.");

			return;
		}

		try{
			$i++;
			Utils::loadFirst($this->data['arena']['arena_world']);
		}catch(\Exception $ex){
			Server::getInstance()->getLogger()->warning("{$this->data['arena']['arena_world']} world is corrupted, trying to reset to its last state. Trial $i");

			$this->reload();
			$this->recheckArena($i);

			return;
		}
		$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);

		$this->task->line1 = str_replace("&", "§", $this->data['signs']['status_line_1']);
		$this->task->line2 = str_replace("&", "§", $this->data['signs']['status_line_2']);
		$this->task->line3 = str_replace("&", "§", $this->data['signs']['status_line_3']);
		$this->task->line4 = str_replace("&", "§", $this->data['signs']['status_line_4']);
	}

	public function forceShutdown(){
		HandlerList::unregisterAll($this->listener);
		$this->plugin->getScheduler()->cancelTask($this->task->getTaskId());
	}

	public function kickPlayer($p){
		/** @var Player[] $players */
		$players = parent::getAllPlayers();
		$players[strtolower($p)]->sendMessage($this->plugin->getMsg($players[strtolower($p)], 'admin-kick'));
		$this->leaveArena($players[strtolower($p)], true);
	}

	public function leaveArena(Player $p, $kicked = false){
		$sound = new EndermanTeleportSound(new Vector3());
		if($this->getPlayerMode($p) == 0){
			if($this->inAcceptedMode() or $kicked){
				unset($this->players[strtolower($p->getName())]);
				if($kicked){
					$this->messageArenaPlayers('leave-others', true, ["%1", "%2"], [$p->getName(), count($this->players)]);
				}
				$this->checkAlive();
				$this->removeCage($p);
				unset($this->spawnPedestals[$p->getName()]);
			}else{
				$p->sendMessage($this->plugin->getMsg($p, 'arena-running'));

				return;
			}
		}

		if($this->getPlayerMode($p) == 1) unset($this->spec[strtolower($p->getName())]);
		if(!$kicked) $p->sendMessage($this->plugin->getMsg($p, 'player-leave-2'));

		$p->teleport($this->plugin->getDatabase()->getLobby());
		$sound->setComponents($p->x, $p->y, $p->z);
		$p->getLevel()->addSound($sound, [$p]);

		# Reset the XP Level
		$p->setXpLevel(0);
		$p->removeAllEffects();
		$p->setGamemode(0);
		$p->getInventory()->clearAll();
		$p->getArmorInventory()->clearAll();

		$this->task->getArenaScoreboard()->removePlayer($p);

		Utils::sendDebug("leaveArena() is being called");
		Utils::sendDebug("User " . $p->getName() . " is leaving the arena.");
	}

	/**
	 * Check if the arena is in acceptable mode
	 * for spectator or arena state
	 *
	 * @return bool
	 */
	public function inAcceptedMode(): bool{
		return $this->getMode() === self::ARENA_WAITING_PLAYERS || $this->getMode() === self::ARENA_CELEBRATING;
	}

	/**
	 * Get the current state of the arena
	 *
	 * @return int
	 */
	public function getMode(): int{
		return $this->game;
	}

	public function messageArenaPlayers(string $msg, $popup = true, $toReplace = [], $replacement = []){
		$inGame = parent::getAllPlayers();
		/** @var Player $p */
		foreach($inGame as $p){
			if($popup){
				$p->sendPopup(str_replace($toReplace, $replacement, $this->plugin->getMsg($p, $msg, false)));
			}else{
				$p->sendPopup(str_replace($toReplace, $replacement, $this->plugin->getMsg($p, $msg, false)));
			}

			$p->getLevel()->addSound(new ClickSound($p));
		}
	}

	public function checkAlive(): bool{
		if(count($this->players) === 1 and $this->getMode() === self::ARENA_RUNNING){
			$this->totalPlayed = 0;
			$this->setGame(self::ARENA_CELEBRATING);
			foreach($this->players as $player){
				$player->setXpLevel(0);
				$player->removeAllEffects();
				$player->setGamemode(0);
				$player->getInventory()->clearAll();
				$player->getArmorInventory()->clearAll();
				$player->setGamemode(Player::SPECTATOR);
				$this->giveGameItems($player, true);
			}

			return true;
		}elseif(empty($this->players) && $this->getMode() !== self::ARENA_WAITING_PLAYERS){
			$this->totalPlayed = 0;
			$this->stopGame(true);
		}

		return true;
	}

	/**
	 * Set the arena-game mode
	 *
	 * @param int $mode
	 */
	public function setGame(int $mode){
		if($mode < Arena::ARENA_WAITING_PLAYERS && $mode > Arena::ARENA_RUNNING){
			throw new InvalidArgumentException("Arena mode must be at least 0-2, $mode given");
		}
		$this->game = $mode;
	}

	/**
	 * Give the player the items that required in config.yml
	 * For spectator or NON-Spectator
	 *
	 * @param Player $p
	 * @param bool $spectate
	 */
	public function giveGameItems(Player $p, bool $spectate){
		if(!Settings::$enableSpecialItem){
			return;
		}
		foreach(Settings::$items as $item){
			/** @var Item $toGive */
			$toGive = $item[0];
			$placeAt = $item[1];
			$itemPermission = $item[2];
			$itemSpectate = $item[3];
			$giveAtWin = $item[6];
			# Set a new compound tag

			if($toGive->getId() === Item::FILLED_MAP){
				$tag = new CompoundTag();
				$tag->setTag(new CompoundTag("", []));
				$tag->setString("map_uuid", 18293883);
				$toGive->setCompoundTag($tag);
			}
			if(empty($itemPermission) || $p->hasPermission($itemPermission)){
				if($giveAtWin && $this->getMode() === Arena::ARENA_CELEBRATING){
					$p->getInventory()->setItem($placeAt, $toGive, true);
					continue;
				}
				if($spectate && $itemSpectate){
					$p->getInventory()->setItem($placeAt, $toGive, true);
					continue;
				}

				if(!$spectate && !$itemSpectate){
					$p->getInventory()->setItem($placeAt, $toGive, true);
					continue;
				} //Squid turning into kid
			}
		}
	}

	public function stopGame($forced = false){
		if(!$forced){
			$this->broadcastResult();
		}
		$this->setGame(self::ARENA_WAITING_PLAYERS);
		$this->unsetAllPlayers();
		$this->reload();

		Utils::sendDebug("stopGame(" . $forced . ") is being called");
	}

	public function broadcastResult(){
		foreach($this->players as $p){
			$p->setXpLevel(0);
		}

		$p = $this->plugin->getServer()->getPlayerExact($this->winners[0][0]);
		if(!is_null($p)){
			$pd = $this->plugin->getDatabase()->getPlayerData($p->getName());
			$pd->time += $this->totalPlayed;
			$pd->wins += 1;
			$pd->kill += $this->winners[0][1];
			$this->plugin->getDatabase()->setPlayerData($p->getName(), $pd);
		}

		foreach($this->winners as $winner){
			$p = $this->plugin->getServer()->getPlayer($winner[0]);
			$this->giveMoney($p, $this->data['arena']['money_reward']);
		}

		/** @var string $playerName */
		$playerName = $this->winners[0][0];
		$playerName = $playerName = isset($this->playerNameFixed[$playerName])
			? $this->playerNameFixed[$playerName] : $playerName;

		# Now the finish message
		$msg = str_replace(['{PLAYER}', '{ARENA}'], [$playerName, $this->getArenaName()], $this->plugin->getMsg($p, 'finish-broadcast-message'));
		$levels = explode(", ", $this->data['arena']['finish_msg_levels']);
		Server::getInstance()->getLogger()->info($msg);
		foreach($levels as $level){
			$lvl = $this->plugin->getServer()->getLevelByName($level);
			if($lvl instanceof Level){
				foreach($lvl->getPlayers() as $p){
					$p->sendMessage($msg);
				}
			}
		}
	}

	public function giveMoney($p, int $money = -1){
		if(is_null($p) || !($p instanceof Player)){
			return;
		}
		if($this->plugin->economy !== null){
			$ec = $this->plugin->economy;
			if($money === 0){
				return;
			}
			$ec->addMoney($p->getName(), $money);
			$p->sendMessage(str_replace('{VALUE}', $money, $this->plugin->getMsg($p, 'player-receive-money')));
		}
	}

	/**
	 * Get the Arena name, This is taken from the
	 * yaml file name
	 *
	 * @return string
	 */
	public function getArenaName(): string{
		return $this->id;
	}

	/**
	 * Unset all of the players in the arena.
	 * Used to reset the players from arena as fast as possible
	 */
	public function unsetAllPlayers(){
		foreach($this->players as $p){
			$p->removeAllEffects();
			$p->setMaxHealth(20);
			$p->setMaxHealth($p->getMaxHealth());
			if($p->getAttributeMap() != null){//just to be really sure
				$p->setHealth(20);
				$p->setFood(20);
			}
			$sound = new EndermanTeleportSound(new Vector3());
			$sound->setComponents($p->x, $p->y, $p->z);
			$p->getLevel()->addSound($sound, [$p]);
			unset($this->players[strtolower($p->getName())]);
			# Reset the XP Level
			$p->setXpLevel(0);
			$p->removeAllEffects();
			$p->setGamemode(0);
			$p->getInventory()->clearAll();
			$p->getArmorInventory()->clearAll();

			$p->teleport($this->plugin->getDatabase()->getLobby());
			$this->task->getArenaScoreboard()->removePlayer($p);
		}
		foreach($this->spec as $p){
			$p->removeAllEffects();
			$p->setMaxHealth(20);
			$p->setMaxHealth($p->getMaxHealth());
			if($p->getAttributeMap() != null){//just to be really sure
				$p->setHealth(20);
				$p->setFood(20);
			}
			$sound = new EndermanTeleportSound(new Vector3());
			$sound->setComponents($p->x, $p->y, $p->z);
			$p->getLevel()->addSound($sound, [$p]);
			unset($this->spec[strtolower($p->getName())]);
			# Reset the XP Level
			$p->setXpLevel(0);
			$p->removeAllEffects();
			$p->setGamemode(0);
			$p->getInventory()->clearAll();
			$p->getArmorInventory()->clearAll();

			$p->teleport($this->plugin->getDatabase()->getLobby());
			$this->task->getArenaScoreboard()->removePlayer($p);
		}
		// Reset the arrays
		$this->spawnPedestals = [];
		$this->claimedPedestals = [];
		$this->players = [];
		$this->winners = [];
		$this->kills = [];
		$this->playerNameFixed = [];

		Utils::sendDebug("unsetAllPlayers() is being called");
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
			$this->level->setBlock($pos, Block::get(0));
		}
		unset($this->cageToRemove[strtolower($p->getName())]);

		return true;
	}

	/**
	 * Get the current status for the arena
	 *
	 * @return string
	 */
	public function getStatus(): string{
		if($this->setup == true){
			return "&eIn setup";
		}
		if($this->disabled == true){
			return "&cDisabled";
		}
		if($this->getMode() === Arena::ARENA_WAITING_PLAYERS){
			return "&fWaiting";
		}
		if($this->getPlayers() >= $this->getMinPlayers()){
			return "&6Starting";
		}
		if($this->getMode() === Arena::ARENA_RUNNING){
			return "&cRunning";
		}
		if($this->getMode() === Arena::ARENA_CELEBRATING){
			return "&cEnded";
		}
		if($this->getPlayers() === $this->getMaxPlayers()){
			return "&cFull";
		}

		return "&eUnknown";
	}

	/**
	 * Arena minimum players
	 *
	 * @return int
	 */
	public function getMinPlayers(){
		return $this->data['arena']['min_players'];
	}

	/**
	 * The arena max players
	 *
	 * @return int
	 */
	public function getMaxPlayers(){
		return $this->data['arena']['max_players'];
	}

	/**
	 * Get the status for the Arena block
	 *
	 * @return StainedGlass
	 */
	public function getBlockStatus(): StainedGlass{
		if($this->setup == true){
			return new StainedGlass(14);
		}
		if($this->disabled == true){
			return new StainedGlass(14);
		}
		if($this->getMode() === Arena::ARENA_WAITING_PLAYERS){
			return new StainedGlass(13);
		}
		if($this->getPlayers() >= $this->getMinPlayers()){
			return new StainedGlass(4);
		}
		if($this->getMode() === Arena::ARENA_RUNNING){
			return new StainedGlass(6);
		}
		if($this->getMode() === Arena::ARENA_CELEBRATING){
			return new StainedGlass(11);
		}

		return new StainedGlass(0);
	}

	/**
	 * Safely teleport player and join to and arena
	 *
	 * @param Player $p
	 */
	public function joinToArena(Player $p){
		if($this->setup){
			$p->sendMessage($this->plugin->getMsg($p, 'arena-insetup'));

			return;
		}
		if($this->getPlayers() >= $this->getMaxPlayers()){
			$p->sendMessage($this->plugin->getMsg($p, 'arena-full'));

			return;
		}
		if($this->getMode() >= self::ARENA_RUNNING){
			$p->sendMessage($this->plugin->getMsg($p, 'arena-running'));

			return;
		}
		if($this->disabled){
			$p->sendMessage($this->plugin->getMsg($p, 'arena-disabled'));

			return;
		}
		$e = new PlayerJoinArenaEvent($this->plugin, $p, $this);
		try{
			$e->call();
		}catch(\ReflectionException $e){
		}

		if($e->isCancelled()){
			return;
		}
		Utils::loadFirst($this->data['arena']['arena_world']); # load the level
		$this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']); # Reset the current state of level

		$p->getInventory()->setHeldItemIndex(1, true);
		$this->messageArenaPlayers('player-join-2', true, ['{PLAYER}'], [$p->getName()]);
		$this->onJoin($p);

		$this->giveGameItems($p, false);
		$this->task->getArenaScoreboard()->addPlayer($p);
	}

	private function onJoin(Player $p){
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
		$this->players[strtolower($p->getName())] = $p;
		$this->kills[strtolower($p->getName())] = 0;

		# Okay saved then we get the spawn for the player
		$spawn = $this->getNextPedestals($p);
		$this->spawnPedestals[$p->getName()] = $spawn;

		# Get the custom cages
		$cageLib = $this->plugin->getCage();
		if($cageLib){
			$cage = $cageLib->getPlayerCage($p);
			$this->cageToRemove[strtolower($p->getName())] = $cage->build($spawn);
		}

		# Teleport them to the arena
		$p->teleport($spawn, 0, 0);

		# Add some sound and all set
		$sound = new EndermanTeleportSound(new Vector3($p->x, $p->y, $p->z));
		$p->getLevel()->addSound($sound, [$p]);

		// This is to fix the lowercased characters.
		// Because everything in this code is lowercased
		$this->playerNameFixed[strtolower($p->getName())] = $p->getName();

		$p->sendMessage(str_replace("{PLAYER}", $p->getName(), $this->plugin->getMsg($p, 'player-join')));
	}

	private function getNextPedestals(Player $player): Position{
		$levelArena = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
		if(empty($this->claimedPedestals)){
			$this->claimedPedestals[$player->getName()] = 1;
			$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . 1];

			return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $levelArena);
		}
		$now = max($this->claimedPedestals);
		# Now the loop where to search if there is an empty space in cage
		for($i = $now; $i > 1; $i--){
			if(array_search($i, $this->claimedPedestals)){
				continue;
			}
			$this->claimedPedestals[$player->getName()] = $i;
			$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . ($i)];

			return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $levelArena);
		}
		# Otherwise return the value + 1
		$now += 1;
		$this->claimedPedestals[$player->getName()] = $now;
		$theSpawn = $this->data["arena"]["spawn_positions"]["spawn" . ($now)];

		return new Position($theSpawn[0] + 0.5, $theSpawn[1], $theSpawn[2] + 0.5, $levelArena);
	}

	/**
	 * Start the game, stop the time and force it to
	 * load.
	 */
	public function startGame(){
		$this->game = 1;
		foreach($this->players as $p){
			if($p instanceof Player){
				$p->setMaxHealth(Settings::$joinHealth);
				$p->setMaxHealth($p->getMaxHealth());
				$p->getInventory()->clearAll();
				$p->getArmorInventory()->clearAll();
				if($p->getAttributeMap() != null){//just to be really sure
					$p->setHealth(Settings::$joinHealth);
					$p->setFood(20);
				}

				$cageLib = $this->plugin->getCage();
				if($cageLib){
					$this->removeCage($p);
					unset($this->spawnPedestals[$p->getName()]);
				}else{
					# Set the block into an air
					$pos = $p->getPosition()->add(0, -1, 0);
					$p->getLevel()->setBlock($pos, Block::get(Block::AIR));
				}

				$p->setXpLevel(0);
				$p->addTitle($this->plugin->getMsg($p, "arena-game-started", false));
				$p->getLevel()->addSound(new GenericSound($p, LevelEventPacket::EVENT_SOUND_ORB, 3));

				Utils::addParticles($p->getLevel(), $p->getPosition()->add(0, -5, 0), 100);
			}
		}

		$this->task->getArenaScoreboard()->setCurrentEvent(TextFormat::RED . "In match");

		$this->refillChests();
		$this->messageArenaPlayers('arena-start', false);
	}

	public function refillChests(){
		$contents = Utils::getChestContents();
		foreach($this->level->getTiles() as $tile){
			if($tile instanceof Chest){
				//CLEARS CHESTS
				$tile->getInventory()->clearAll();
				//SET CONTENTS
				if(empty($contents))
					$contents = Utils::getChestContents();
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

	/**
	 * Get the arena level-name
	 * use this method for safer fetch
	 *
	 * @return string
	 */
	public function getLevelName(): string{
		return $this->data['arena']['arena_world'];
	}

	/**
	 * Return of the level of this arena
	 * Sometimes, it will return null
	 *
	 * @return Level
	 */
	public function getArenaLevel(): Level{
		return $this->level;
	}

	/**
	 * This supposed to return the mode of the
	 * arena which is SOLO or TEAM.
	 * TODO: Implement this fully.
	 *
	 * @return string
	 */
	public function getArenaMode(){
		return "SOLO";
	}
}
