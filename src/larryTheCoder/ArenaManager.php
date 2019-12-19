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

use larryTheCoder\arena\Arena;
use larryTheCoder\arena\State;
use larryTheCoder\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\utils\Config;

final class ArenaManager {

	/** @var string[] */
	public $arenaRealName = [];
	/** @var Arena[] */
	private $arenas = [];
	/** @var array */
	private $arenaConfig = [];
	/** @var SkyWarsPE */
	private $pl;

	public function __construct(SkyWarsPE $plugin){
		$this->pl = $plugin;
	}

	// Check and passed.
	public function checkArenas(){
		$this->pl->getServer()->getLogger()->info($this->pl->getPrefix() . "§6Locating arena files...");
		foreach(glob($this->pl->getDataFolder() . "arenas/*.yml") as $file){
			$arena = new Config($file, Config::YAML);
			$arenaName = basename($file, ".yml");

			$this->arenaRealName[strtolower($arenaName)] = $arenaName;
			$this->arenaConfig[strtolower($arenaName)] = $arena->getAll();

			$baseArena = new Arena($arenaName, $this->pl);
			if(!$baseArena->configChecked){
				unset($this->arenaRealName[strtolower($arenaName)]);
				unset($this->arenaConfig[strtolower($arenaName)]);
				continue;
			}
			$baseArena->resetArena();

			$this->arenas[strtolower($arenaName)] = $baseArena;
		}
	}

	public function reloadArena($arenaF): bool{
		$arenaName = $this->getRealArenaName($arenaF);
		$this->pl->getServer()->getLogger()->info($this->pl->getPrefix() . "§aReloading arena§e $arenaName");
		if(!$this->arenaExist($arenaName)){
			Utils::sendDebug("[reloadArena] §cArena§e $arenaName doesn't exists.");

			return false;
		}

		$arenaConfig = new Config($this->pl->getDataFolder() . "arenas/$arenaName.yml");
		$game = $this->getArena($arenaName);
		# Arena is null but how?
		if(is_null($game) || is_null($arenaConfig)){
			Utils::sendDebug("[reloadArena] §cArena§e $arenaName exists but null.");

			return false;
		}
		$game->inSetup = false;
		$game->resetArena();

		return true;
	}

	// Checked and passed
	public function getRealArenaName($lowerCasedArena){
		if(!isset($this->arenaRealName[strtolower($lowerCasedArena)])){
			return $lowerCasedArena;
		}

		return $this->arenaRealName[strtolower($lowerCasedArena)];
	}

	// Checked and passed
	public function setArenaData(Config $config, $arenaName){
		$arena = $this->getArena($arenaName);
		$arena->setData($config);
		$arena->resetArena();
	}

	// Checked and passed
	public function getArena($arena){
		if(!$this->arenaExist($arena)){
			return null;
		}

		return $this->arenas[strtolower($arena)];
	}

	public function arenaExist(string $arena): bool{
		return isset($this->arenas[strtolower($arena)]);
	}

	public function deleteArena($arena){
		if($this->arenaExist($arena)){
			$this->getArena($arena)->forceShutdown();
			unset($this->arenas[strtolower($arena)]);
			unset($this->arenaConfig[strtolower($arena)]);
		}
	}

	public function getPlayerArena(Player $p): ?Arena{
		foreach($this->arenas as $arena){
			if($arena->isInArena($p)){
				return $arena;
			}
		}

		return null;
	}

	public function getArenaConfig($arenaName){
		if(!isset($this->arenaConfig[strtolower($arenaName)])){
			return null;
		}

		return $this->arenaConfig[strtolower($arenaName)];
	}

	public function getAvailableArena(): ?Arena{
		$arena = $this->getArenas();
		# Check if there is a player in one of the arenas
		foreach($arena as $selector){
			if(!empty($selector->getPlayers()) && $selector->getStatus() <= State::STATE_SLOPE_WAITING){
				return $selector;
			}
		}

		# Otherwise we need to randomize the arena
		# By not letting the player to join a started arena
		$arenas = [];
		foreach($arena as $selector){
			if($selector->getStatus() <= State::STATE_SLOPE_WAITING && $selector->arenaEnable){
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

	public function getArenas(){
		return $this->arenas;
	}

	public function getArenaByInt(int $id): Arena{
		$arenas = [];
		foreach($this->getArenas() as $arena){
			$arenas[] = $arena;
		}

		return $arenas[$id];
	}

	public function isInLevel(Entity $sender): bool{
		foreach($this->arenas as $arena){
			// Lower cased, no wEIrD aESs tEsxTs
			if(strtolower($arena->arenaWorld) === strtolower($sender->getLevel()->getName())){
				return true;
			}
		}

		return false;
	}

	public function invalidate(){
		$this->arenaRealName = [];
		$this->arenas = [];
		$this->arenaConfig = [];
	}
}