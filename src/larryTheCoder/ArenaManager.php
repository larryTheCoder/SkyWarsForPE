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

namespace larryTheCoder;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\utils\Config;

final class ArenaManager {

	/** @var string[] */
	public $arenaRealName = [];
	/** @var ArenaImpl[] */
	private $arenas = [];
	/** @var Config[] */
	private $arenaConfig = [];
	/** @var SkyWarsPE */
	private $pl;

	public function __construct(SkyWarsPE $plugin){
		$this->pl = $plugin;
	}

	// Check and passed.
	public function checkArenas(): void{
		$this->pl->getServer()->getLogger()->info(Settings::$prefix . "§6Locating arena files...");

		$folder = glob($this->pl->getDataFolder() . "arenas/*.yml");
		if($folder === false) throw new \RuntimeException("Unexpected error has occurred while indexing arenas files.");

		foreach($folder as $file){
			$arena = new Config($file, Config::YAML);
			$arenaName = $arena->get("arena-name", null);
			$baseName = basename($file, ".yml");

			if($arenaName === null){
				Utils::send("§6" . ucwords($baseName) . " §a§l-§r§c Config file is not valid.");

				continue;
			}

			$this->arenaRealName[strtolower($arenaName)] = $arenaName;
			$this->arenaRealName[strtolower($baseName)] = $arenaName;
			$this->arenaConfig[strtolower($arenaName)] = $arena;

			$baseArena = new ArenaImpl($this->pl, $arena->getAll());
			if(!$baseArena->configChecked){
				unset($this->arenaRealName[strtolower($arenaName)]);
				unset($this->arenaRealName[strtolower($baseName)]);
				unset($this->arenaConfig[strtolower($arenaName)]);
				continue;
			}

			$this->arenas[strtolower($arenaName)] = $baseArena;
		}
	}

	public function reloadArena(string $arenaF): bool{
		$arenaName = $this->getRealArenaName($arenaF);
		$this->pl->getServer()->getLogger()->info(Settings::$prefix . "§aReloading arena§e $arenaName");
		if(!$this->arenaExist($arenaName)){
			Utils::sendDebug("[reloadArena] Arena $arenaName doesn't exists.");

			return false;
		}

		$arenaConfig = $this->getArenaConfig($arenaName);
		$game = $this->getArena($arenaName);
		# Arena is null but how?
		if(is_null($game) || is_null($arenaConfig)){
			Utils::sendDebug("[reloadArena] Arena $arenaName exists but null.");

			return false;
		}
		$game->setFlags(Arena::ARENA_IN_SETUP_MODE, false);

		return true;
	}

	// Checked and passed
	public function getRealArenaName(string $lowerCasedArena): string{
		if(!isset($this->arenaRealName[strtolower($lowerCasedArena)])){
			return $lowerCasedArena;
		}

		return $this->arenaRealName[strtolower($lowerCasedArena)];
	}

	// Checked and passed
	public function setArenaData(Config $config, string $arenaName): void{
		$arena = $this->getArena($arenaName);
		if($arena === null){
			$this->arenaRealName[strtolower($arenaName)] = $arenaName;
			$this->arenaConfig[strtolower($arenaName)] = $config;

			// Create a new arena if it doesn't exists.
			$baseArena = new ArenaImpl($this->pl, $config->getAll());
			if(!$baseArena->configChecked){
				unset($this->arenaRealName[strtolower($arenaName)]);
				unset($this->arenaConfig[strtolower($arenaName)]);

				return;
			}

			$arena = $this->arenas[strtolower($arenaName)] = $baseArena;
		}

		$arena->setConfig($config->getAll());
	}

	// Checked and passed
	public function getArena(string $arena): ?ArenaImpl{
		if(!$this->arenaExist($arena)){
			Utils::sendDebug("getArena($arena): Not found");

			return null;
		}
		Utils::sendDebug("getArena($arena): Found data type.");

		return $this->arenas[strtolower($arena)];
	}

	public function arenaExist(string $arena): bool{
		return isset($this->arenas[strtolower($arena)]);
	}

	public function deleteArena(string $arena): void{
		if($this->arenaExist($arena)){
			$this->getArena($arena)->shutdown();
			unset($this->arenas[strtolower($arena)]);
			unset($this->arenaConfig[strtolower($arena)]);
		}
	}

	public function getPlayerArena(Player $p): ?ArenaImpl{
		foreach($this->arenas as $arena){
			if($arena->getPlayerManager()->isInArena($p)){
				return $arena;
			}
		}

		return null;
	}

	public function getArenaConfig(string $arenaName): ?Config{
		if(!isset($this->arenaConfig[strtolower($arenaName)])){
			return null;
		}

		return $this->arenaConfig[strtolower($arenaName)];
	}

	public function getAvailableArena(): ?ArenaImpl{
		$arena = $this->getArenas();
		# Check if there is a player in one of the arenas
		foreach($arena as $selector){
			if(!empty($selector->getPlayerManager()->getAlivePlayers()) && $selector->getStatus() <= ArenaState::STATE_STARTING){
				return $selector;
			}
		}

		# Otherwise we need to randomize the arena
		# By not letting the player to join a started arena
		$arenas = [];
		foreach($arena as $selector){
			if($selector->getStatus() <= ArenaState::STATE_STARTING && $selector->arenaEnable){
				$arenas[] = $selector;
			}
		}

		# There were 0 arenas found
		if(empty($arenas)){
			return null;
		}

		# Otherwise randomize it and put it into return arena.
		return $arenas[mt_rand(0, count($arenas) - 1)];
	}

	/**
	 * @return ArenaImpl[]
	 */
	public function getArenas(): array{
		return $this->arenas;
	}

	public function getArenaByInt(int $id): ArenaImpl{
		$arenas = [];
		foreach($this->getArenas() as $arena){
			$arenas[] = $arena;
		}

		return $arenas[$id];
	}

	public function insideArenaLevel(Entity $entity): bool{
		return !empty(array_filter($this->arenas, function($value) use ($entity): bool{
			return $value->getLevel()->getFolderName() === $entity->getLevel()->getFolderName();
		}));
	}

	public function invalidate(): void{
		$this->arenaRealName = [];
		$this->arenas = [];
		$this->arenaConfig = [];
	}
}