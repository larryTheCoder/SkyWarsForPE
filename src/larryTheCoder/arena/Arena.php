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

use larryTheCoder\events\PlayerJoinArenaEvent;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\{scoreboard\Action,
	scoreboard\DisplaySlot,
	scoreboard\Scoreboard,
	scoreboard\Sort,
	Settings,
	Utils};
use pocketmine\{item\enchantment\Enchantment, item\enchantment\EnchantmentInstance, Player, Server};
use pocketmine\block\{Block, StainedGlass};
use pocketmine\entity\Entity;
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
class Arena {

	const ARENA_WAITING_PLAYERS = 0;
	const ARENA_RUNNING = 1;
	const ARENA_CELEBRATING = 2;

	/** @var array */
	public $data;
	/** @var Player[] */
	public $players = [];
	/** @var Player[] */
	public $spec = [];
	/** @var Position */
	public $cageToRemove = [];
	/** @var integer[] */
	public $claimedPedestals = [];
	/** @var Position[] */
	public $spawnPedestals = [];
	/** @var array */
	public $kills = [];
	/** @var string[] */
	public $winners = [];
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
	/** @var Scoreboard */
	private $score;

	public function __construct(string $id, SkyWarsPE $plugin){
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

		$this->score = new Scoreboard($this->data['arena-name'] . " Arena", Action::CREATE);
		$this->score->create(DisplaySlot::SIDEBAR, Sort::ASCENDING);
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

		Utils::ensureDirectory($this->plugin->getDataFolder() . 'arenas/worlds');
		if(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar') || is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz')){
			return;
		}

		$tar = new \PharData($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar');
		$tar->startBuffering();
		$tar->buildFromDirectory(realpath(Server::getInstance()->getDataPath() . 'worlds/' . $levelName));
		$tar->stopBuffering();
		unset($tar);
	}

	/**
	 * @return bool
	 */
	public function reload(): bool{
		$levelName = $this->data['arena']['arena_world'];
		if($this->plugin->getServer()->isLevelLoaded($levelName)){
			$this->checkLevelTime();
			$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($levelName));
		}
		$levelName = $this->data["arena"]["arena_world"];
		$this->fallTime = $this->data['arena']['grace_time'];

		$this->deleteDirectory(Server::getInstance()->getDataPath() . 'worlds/' . $levelName);
		if($this->plugin->getServer()->isLevelLoaded($levelName)){
			if($this->plugin->getServer()->getLevelByName($levelName)->getAutoSave() || Settings::$zipArchive){
				$this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($levelName));
				if(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar')){
					$tar = new \PharData($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar');
				}elseif(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz')){
					$tar = new \PharData($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz');
				}else{
					# Will never reach this.
					return false;
				}
				$tar->extractTo(Server::getInstance()->getDataPath() . 'worlds/' . $levelName, null, true);
				unset($tar);
			}
		}else{
			if(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar')){
				$tar = new \PharData($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar');
			}elseif(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz')){
				$tar = new \PharData($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz');
			}else{
				# Will never reach this.
				return false;
			}
			$tar->extractTo(Server::getInstance()->getDataPath() . 'worlds/' . $levelName, null, true);
			unset($tar);
		}
		if(is_file($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar.gz')){
			@unlink($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar');
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

	public function deleteDirectory($dir){
		if(!file_exists($dir)){
			return true;
		}

		if(!is_dir($dir)){
			return unlink($dir);
		}

		foreach(scandir($dir) as $item){
			if($item == '.' || $item == '..'){
				continue;
			}

			if(!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)){
				return false;
			}

		}

		return rmdir($dir);
	}

	/**
	 * Reset the world data
	 */
	public function reset(){
		$levelName = $this->data['arena']['arena_world'];

		unlink($this->plugin->getDataFolder() . 'arenas/worlds/' . $levelName . '.tar');
		$this->saveArenaWorld();
	}

	public function recheckArena(){
		Utils::loadFirst($this->data['arena']['arena_world']);
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
		$players = array_merge($this->players, $this->spec);
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

		$p->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
		$sound->setComponents($p->x, $p->y, $p->z);
		$p->getLevel()->addSound($sound, [$p]);

		# Reset the XP Level
		$p->setXpLevel(0);
		$p->removeAllEffects();
		$p->setGamemode(0);
		$p->getInventory()->clearAll();
		$p->getArmorInventory()->clearAll();

		// Remove his scoreboard display.
		$this->score->removeDisplay($p);

		Utils::sendDebug("leaveArena() is being called");
		Utils::sendDebug("User " . $p->getName() . " is leaving the arena.");
	}

	/**
	 * @param Player $p
	 * @return bool|int
	 */
	public function getPlayerMode(Player $p){
		if(isset($this->players[strtolower($p->getName())])){
			return 0;
		}
		if(isset($this->spec[strtolower($p->getName())])){
			return 1;
		}

		return false;
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
		$inGame = array_merge($this->players, $this->spec);
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
				}
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
		$pd = $this->plugin->getDatabase()->getPlayerData($p->getName());
		$pd->time += $this->totalPlayed;
		$pd->wins += 1;
		$pd->kill += $this->winners[0][1];
		$this->plugin->getDatabase()->setPlayerData($p->getName(), $pd);
		$pd = $this->plugin->getDatabase()->getPlayerData($p->getName());
		Server::getInstance()->getLogger()->info($pd);

		foreach($this->winners as $winner){
			$p = $this->plugin->getServer()->getPlayer($winner[0]);
			$this->giveMoney($p, $this->data['arena']['money_reward']);
		}

		# Now the finish message
		$msg = str_replace(['{PLAYER}', '{ARENA}'], [$this->winners[0][0], $this->getArenaName()], $this->plugin->getMsg($p, 'finish-broadcast-message'));
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
			$p->teleport($this->plugin->getDatabase()->getLobby());
			# Reset the XP Level
			$p->setXpLevel(0);
			$p->removeAllEffects();
			$p->setGamemode(0);
			$p->getInventory()->clearAll();
			$p->getArmorInventory()->clearAll();
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
			$p->teleport($this->plugin->getDatabase()->getLobby());
			# Reset the XP Level
			$p->setXpLevel(0);
			$p->removeAllEffects();
			$p->setGamemode(0);
			$p->getInventory()->clearAll();
			$p->getArmorInventory()->clearAll();
		}
		// Reset the arrays
		$this->spawnPedestals = [];
		$this->claimedPedestals = [];
		$this->players = [];
		$this->winners = [];
		$this->kills = [];

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
	 * Re-update the status list from data, this will ensure that
	 * the highest kills will be recorded
	 */
	public function statusUpdate(){
		if($this->getMode() !== self::ARENA_WAITING_PLAYERS){
			$i = 0;
			arsort($this->kills);
			foreach($this->kills as $player => $kills){
				$p = $this->plugin->getServer()->getPlayer($player);
				if(!is_null($p)){
					$this->winners[$i] = ["{$p->getName()}", $kills];
				}else{
					unset($this->kills[$player]);
				}
				$i++;
			}
		}else{
			$this->winners = [];
			# Sometimes player are null
			if(!isset($this->winners[1])){
				$this->winners[0] = ["§7...", 0];
			}
			if(!isset($this->winners[2])){
				$this->winners[1] = ["§7...", 0];
			}
			if(!isset($this->winners[3])){
				$this->winners[2] = ["§7...", 0];
			}
		}
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
		if(count($this->players) >= $this->getMinPlayers()){
			return "&6Starting";
		}
		if($this->getMode() === Arena::ARENA_RUNNING){
			return "&cRunning";
		}
		if($this->getMode() === Arena::ARENA_CELEBRATING){
			return "&cEnded";
		}
		if(count($this->players) === $this->getMaxPlayers()){
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
		if(count($this->players) >= $this->getMinPlayers()){
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
		if(count($this->players) >= $this->getMaxPlayers()){
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

		// Add the display to the player.
		$this->score->addDisplay($p);
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
		$this->plugin->getServer()->getLogger()->info($spawn);
		$p->teleport($spawn, 0, 0);

		# Add some sound and all set
		$sound = new EndermanTeleportSound(new Vector3($p->x, $p->y, $p->z));
		$p->getLevel()->addSound($sound, [$p]);

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

				$p->addTitle($this->plugin->getMsg($p, "arena-game-started", false));
				$p->setXpLevel(0);
				$p->getLevel()->addSound(new GenericSound($p, LevelEventPacket::EVENT_SOUND_ORB, 3));

				Utils::addParticles($p->getLevel(), $p->getPosition()->add(0, -5, 0), 100);
			}
		}

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
	 * Check if the entity is in this arena
	 *
	 * @param Entity $p
	 * @return bool
	 */
	public function inArena(Entity $p): bool{
		if(!($p instanceof Player)){
			return false;
		}
		$players = array_merge($this->players, $this->spec);

		return isset($players[strtolower($p->getName())]);
	}

	/**
	 * Get the numbers of player in arena
	 *
	 * @return int
	 */
	public function getPlayers(): int{
		return count($this->players);
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
	 * Gets the scoreboard class for this arena,
	 * each arena will be given a separated scoreboard
	 * classes.
	 */
	public function getScoreboard(): Scoreboard{
		return $this->score;
	}
}
