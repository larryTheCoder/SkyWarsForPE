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

namespace larryTheCoder\libs\cages;

use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use onebone\economyapi\EconomyAPI;
use pocketmine\{Player, Server};
use pocketmine\item\Item;
use pocketmine\utils\Config;

class ArenaCage {

	public $cage = [];
	/** @var SkyWarsPE */
	private $plugin;
	/** @var Cage[] */
	private $playerCages;
	/** @var Cage */
	private $defaultCage = null;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;
		$this->loadCage();
	}

	public function loadCage(){
		$this->plugin->saveResource("cages.yml");
		$cfg = new Config($this->plugin->getDataFolder() . "cages.yml", Config::YAML);
		if($cfg->get('version-id') < 1){
			Server::getInstance()->getLogger()->info($this->plugin->getPrefix() . "Old cage config detected. Renaming to cages.yml.old");
			rename($this->plugin->getDataFolder() . "cages.yml", $this->plugin->getDataFolder() . "cages.yml.old");
			$this->plugin->saveResource("cages.yml");
		}

		foreach(array_keys($cfg->get("cages")) as $value){
			$name = $cfg->getNested("cages.$value.cage-name");
			$valued = $cfg->getNested("cages.$value.cage-price");
			$default = $cfg->getNested("cages.$value.cage-default");
			$array = ["floor", "lower-middle", "middle", "higher-middle", "roof"];
			$array2 = [];
			for($j = 0; $j < 5; ++$j){
				$item = $cfg->getNested("cages.$value.$array[$j]");
				$array2[$j] = Utils::convertToBlock($item);
			}
			$cage = new Cage($name, $valued, $array2);
			$this->cage[strtolower($name)] = $cage;
			if($default && is_null($this->defaultCage)){
				$this->defaultCage = $cage;
			}
		}

		if(is_null($this->defaultCage)){
			# Preload the default cage
			$array2 = [];
			for($j = 0; $j < 4; ++$j){
				$array2[$j] = Item::get(20);
			}
			$this->defaultCage = new Cage("Default Cage", 0, $array2);
		}

	}

	/**
	 * Set the cage for the player
	 *
	 * @param Player $player
	 * @param Cage $cage
	 */
	public function setPlayerCage(Player $player, Cage $cage){
		$pd = $this->plugin->getDatabase()->getPlayerData($player->getName());
		if(!in_array(strtolower($cage->getCageName()), $pd->cages)){
			$this->buyCage($player, $cage);

			return;
		}
		$this->playerCages[$player->getName()] = $cage;
		$player->sendMessage($this->plugin->getPrefix() . "§aChosen cage §7" . $cage->getCageName());
	}

	public function buyCage(Player $p, Cage $cage){
		$playerData = $this->plugin->getDatabase()->getPlayerData($p->getName());
		if(in_array(strtolower($cage->getCageName()), $playerData->cages)){
			$p->sendMessage("You already bought this cage");

			return;
		}

		$price = $cage->getPrice();
		if($this->plugin->economy->myMoney($p) < $price){
			$p->sendMessage("You don't have enough money to buy this");

			return;
		}

		$ret = $this->plugin->economy->reduceMoney($p->getName(), $price);
		if($ret !== EconomyAPI::RET_SUCCESS){
			$p->sendMessage("Cannot process your payment. Try again later");

			return;
		}

		$playerData->cages[] = strtolower($cage->getCageName());
		$this->plugin->getDatabase()->setPlayerData($p->getName(), $playerData);
		$this->setPlayerCage($p, $cage);
	}

	/**
	 * Get the list of cages
	 *
	 * @return Cage[]
	 */
	public function getCages(): array{
		return $this->cage;
	}

	/**
	 * Get the player cage
	 *
	 * @param Player $p
	 * @return Cage
	 */
	public function getPlayerCage(Player $p){
		return isset($this->playerCages[$p->getName()]) ? $this->playerCages[$p->getName()] : $this->defaultCage;
	}
}