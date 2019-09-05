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

use larryTheCoder\arenaRewrite\Arena;
use larryTheCoder\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\utils\Config;

final class ArenaManagerRewrite {

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
			# How this could possibly been?
			if(Utils::checkFile($arena) === false){
				$this->pl->getServer()->getLogger()->warning("§cFile §7$arenaName §ccould not be loaded.");
				continue;
			}
			$this->arenaRealName[strtolower($arenaName)] = $arenaName;
			$this->arenaConfig[strtolower($arenaName)] = $arena->getAll();
			$this->arenas[strtolower($arenaName)] = new Arena($arenaName, $this->pl);
			# Two function (Enabled | Disabled)
			if($arena->get("enabled") === true){
				$this->arenas[strtolower($arenaName)]->disabled = false;
				$this->pl->getServer()->getLogger()->info($this->pl->getPrefix() . "§6" . ucwords($arenaName) . " §a§l-§r§a Arena loaded and enabled");
			}else{
				$this->arenas[strtolower($arenaName)]->disabled = true;
				$this->pl->getServer()->getLogger()->info($this->pl->getPrefix() . "§6" . ucwords($arenaName) . " §a§l-§r§c Arena disabled");
			}
		}
	}

	public function reloadArena($arenaF): bool{
		return true; // TODO
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
			if(!empty($selector->getPlayers()) && $selector->getStatus() <= Arena::STATE_SLOPE_WAITING){
				return $selector;
			}
		}

		# Otherwise we need to randomize the arena
		# By not letting the player to join a started arena
		$arenas = [];
		foreach($arena as $selector){
			if($selector->getStatus() <= Arena::STATE_SLOPE_WAITING && $selector->arenaEnable){
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
			if(strtolower($arena->getArenaLevel()->getName()) === strtolower($sender->getLevel()->getName())){
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