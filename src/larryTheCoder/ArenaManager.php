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

use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\task\AsyncDirectoryDelete;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\utils\ConfigManager;
use larryTheCoder\utils\Utils;
use larryTheCoder\worker\LevelAsyncPool;
use pocketmine\Player;
use pocketmine\utils\Config;

final class ArenaManager {

	/** @var SkyWarsPE */
	private $plugin;

	/** @var ArenaImpl[] */
	private $arenas = [];
	/** @var ConfigManager[] */
	private $config;

	public function __construct(){
		$this->plugin = SkyWarsPE::getInstance();
	}

	public function checkArenas(): void{
		foreach(glob($this->plugin->getDataFolder() . "arenas/*.yml") as $configPath){
			$cm = new ConfigManager(basename($configPath, ".yml"), new Config($configPath, Config::YAML));

			if($cm->arenaName === null){
				Utils::send("§6" . ucwords($cm->fileName) . " §a§l-§r§c Config file is missing its arena name.");

				continue;
			}

			$this->arenas[$cm->arenaName] = new ArenaImpl($this->plugin, $cm);
			$this->config[$cm->arenaName] = $cm;
		}
	}

	/**
	 * @return ArenaImpl[]
	 */
	public function getArenas(): array{
		return $this->arenas;
	}

	/**
	 * Returns an arena instance of associated arena name. This arena name must be
	 * exactly the name of the arena as it is name-strict.
	 *
	 * @param string $arena
	 * @return ArenaImpl|null
	 */
	public function getArena(string $arena): ?ArenaImpl{
		return $this->arenas[$arena] ?? null;
	}

	/**
	 * Retrieves the config mapping system from the memory array.
	 * {@see AMRewrite::getArena()}
	 *
	 * @param string $arena
	 * @return ConfigManager|null
	 */
	public function getConfig(string $arena): ?ConfigManager{
		return $this->config[$arena] ?? null;
	}

	/**
	 * Creates a skeleton for Arena class, this will copy the arena config file to designated location
	 * and returns a temporary arena class which is in setup mode.
	 *
	 * @param string $arenaName
	 * @return ArenaImpl
	 */
	public function createArena(string $arenaName): ArenaImpl{
		$configPath = $this->plugin->getDataFolder() . "arenas/$arenaName.yml";
		file_put_contents($configPath, $resource = $this->plugin->getResource('arenas/default.yml'));

		fclose($resource);

		$cm = new ConfigManager(basename($configPath, ".yml"), new Config($configPath, Config::YAML));
		$arena = new ArenaImpl($this->plugin, $cm);

		$this->arenas[$cm->arenaName] = $arena;
		$this->config[$cm->arenaName] = $cm;

		$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, true);

		return $arena;
	}

	/**
	 * Deletes an arena safely from the memory. This function uses the {@link ShutdownSequence} to close
	 * all related tasks and events in the arena.
	 *
	 * @param ArenaImpl $arena
	 */
	public function deleteArena(ArenaImpl $arena): void{
		$task = new AsyncDirectoryDelete([$arena->getLevel()], function() use ($arena): void{
			unlink($arena->getConfigManager()->getConfig()->getPath());

			$arena->shutdown();

			unset($this->arenas[$arena->getMapName()]);
			unset($this->config[$arena->getMapName()]);
		});

		LevelAsyncPool::getAsyncPool()->submitTask($task);
	}

	/**
	 * Returns an available arena, the first entry that has players in them will always be chosen
	 * first to provide better gameplay within random arenas entries.
	 *
	 * @return ArenaImpl|null
	 */
	public function getAvailableArena(): ?ArenaImpl{
		// Check if there is a player in one of the arenas
		foreach($this->arenas as $selector){
			if(!empty($selector->getPlayerManager()->getAlivePlayers()) && $selector->getStatus() <= ArenaState::STATE_STARTING){
				return $selector;
			}
		}

		// Filter the arena to retrieves which arena is enabled and is ready.
		$arenas = array_filter($this->arenas, function($arena): bool{
			return $arena->getStatus() <= ArenaState::STATE_STARTING && !($arena->hasFlags(ArenaImpl::ARENA_IN_SETUP_MODE) || $arena->hasFlags(ArenaImpl::ARENA_CRASHED) || $arena->hasFlags(ArenaImpl::ARENA_DISABLED));
		});

		return empty($arenas) ? null : $arenas[array_rand($arenas)];
	}

	/**
	 * Return the associated arena implementation if the player appears to be in the arena
	 * world. This method will still returns the arena class if the player appears to not be
	 * in arena system.
	 *
	 * @param Player $player
	 * @return ArenaImpl|null
	 */
	public function getPlayerArena(Player $player): ?ArenaImpl{
		$result = array_values(array_filter($this->arenas, function($arena) use ($player): bool{
			return $arena->getPlayerManager()->isInArena($player) || $arena->getLevelName() === $player->getLevel()->getFolderName();
		}));

		return $result[0] ?? null;
	}

	public function invalidate(): void{
		unset($this->arenas, $this->config);
		$this->config = $this->config = [];
	}
}