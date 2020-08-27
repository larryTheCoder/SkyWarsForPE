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

namespace larryTheCoder\features\kits;

use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;

/**
 * This kit handles the player kit.
 * Storing objects for the player kit.
 *
 * @package larryTheCoder\kits
 */
class Kits {

	/** @var int */
	public static $kitsID;
	/** @var KitsAPI[] */
	private $kits;
	/** @var KitsAPI[] */
	private $playerKit;
	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(SkyWarsPE $plugin){
		Kits::$kitsID = 0;
		$this->plugin = $plugin;
	}

	public function registerKit(KitsAPI $api){
		if(isset($this->kits[$api->getKitName()])){
			throw new \LogicException("Could not add kit UUID#{$api->getKitName()}, data already exists!");
		}
		$this->kits[$api->getKitName()] = $api;
		Server::getInstance()->getLogger()->info($this->plugin->getPrefix() . "§aRegistered Kit:§e {$api->getKitName()}.");
	}

	/**
	 * Get the kit for the player
	 *
	 * @param string $name
	 * @return KitsAPI|null
	 */
	public function getPlayerKit(string $name): ?KitsAPI{
		if(isset($this->playerKit[$name])){
			return $this->playerKit[$name];
		}

		return null;
	}

	/**
	 * Set the player kit, set to null to unset the
	 * player kits.
	 *
	 * @param Player $player
	 * @param KitsAPI $kit
	 */
	public function setPlayerKit(Player $player, KitsAPI $kit){
		if(isset($this->playerKit[$player->getName()])) unset($this->playerKit[$player->getName()]);

		$reflect = $this;
		$this->plugin->getDatabase()->getPlayerData($player->getName(), function(PlayerData $pd) use ($player, $reflect, $kit){
			if(!in_array(strtolower($kit->getKitName()), $pd->kitId)){
				$reflect->buyKit($player, $kit);

				return;
			}

			$reflect->playerKit[$player->getName()] = $kit;
			$player->sendMessage($reflect->plugin->getPrefix() . "§aYou have selected §e{$kit->getKitName()} kit.");
		});
	}

	/**
	 * Buy a kit for player, then select them.
	 * By bool $select
	 *
	 * @param Player $p
	 * @param bool $select
	 * @param KitsAPI $kit
	 */
	public function buyKit(Player $p, KitsAPI $kit, bool $select = true){
		$reflect = $this;
		$this->plugin->getDatabase()->getPlayerData($p->getName(), function(PlayerData $playerData) use ($p, $kit, $select, $reflect){
			if(in_array(strtolower($kit->getKitName()), $playerData->kitId)){
				$p->sendMessage("You already bought this kit");

				return;
			}

			$price = $kit->getKitPrice();
			if($reflect->plugin->economy->myMoney($p) < $price){
				$p->sendMessage("You don't have enough money to buy this");

				return;
			}

			$ret = $reflect->plugin->economy->reduceMoney($p->getName(), $price);
			if($ret !== EconomyAPI::RET_SUCCESS){
				$p->sendMessage("Cannot process your payment. Try again later");

				return;
			}

			$playerData->kitId[] = strtolower($kit->getKitName());
			$reflect->plugin->getDatabase()->setPlayerData($p->getName(), $playerData);

			if($select) $reflect->setPlayerKit($p, $kit);
		});
	}

	/**
	 * Get the list of cages
	 *
	 * @return KitsAPI[]
	 */
	public function getKits(): array{
		return $this->kits;
	}

	/**
	 * Get the kits by the id.
	 *
	 * @param int $id
	 * @return KitsAPI
	 */
	public function getKitById(int $id): ?KitsAPI{
		return isset($this->kits[$id]) ? $this->kits[$id] : null;
	}
}