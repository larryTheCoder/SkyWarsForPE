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

namespace larryTheCoder\utils\cage;

use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\permission\PluginPermission;
use larryTheCoder\utils\PlayerData;
use larryTheCoder\utils\Utils;
use onebone\economyapi\EconomyAPI;
use pocketmine\{block\BlockFactory, Player, utils\Config};

class CageManager {

	/** @var CageManager|null */
	public static $instance = null;

	/** @var Cage[] */
	public $cage = [];
	/** @var Cage[] */
	private $cagesSet;
	/** @var Cage|null */
	private $defaultCage = null;

	public static function init(Config $config): void{
		if(self::$instance === null){
			self::$instance = new CageManager();
		}

		self::$instance->loadConfig($config);
	}

	public static function getInstance(): CageManager{
		return self::$instance;
	}

	public function loadConfig(Config $config): void{
		$this->cage = [];
		$this->cagesSet = [];
		$this->defaultCage = null;

		foreach(array_keys($config->get("cages")) as $value){
			$cageName = $config->getNested("cages.$value.cage-name", "default");
			$cagePrice = $config->getNested("cages.$value.cage-price", 0);
			$permission = $config->getNested("cages.$value.permission", "");
			$default = $config->getNested("cages.$value.cage-default", false);
			$blocks = [];

			$i = 0;
			foreach(["floor", "lower-middle", "middle", "higher-middle", "roof"] as $keys){
				$blocks[$i++] = Utils::convertToBlock($config->getNested("cages.$value.$keys"));
			}

			$cage = new Cage($cageName, $cagePrice, $default ? "" : $permission, $blocks);
			$this->cage[$cageName] = $cage;
			if($default && $this->defaultCage === null){
				$this->defaultCage = $cage;
			}
		}

		if($this->defaultCage === null){
			// Load default cages for this server, smh the server owner tries
			// to outsmart the code but failed.
			$blocks = [];
			for($j = 0; $j < 4; ++$j){
				$blocks[$j] = BlockFactory::get(20);
			}

			$this->defaultCage = new Cage("Default Cage", 0, "", $blocks);
			$this->cage["Default Cage"] = $this->defaultCage;
		}
	}

	/**
	 * Set the cage for the player
	 *
	 * @param Player $player
	 * @param Cage $cage
	 */
	public function setPlayerCage(Player $player, Cage $cage): void{
		if(!empty($cage->getCagePermission()) && !$player->hasPermission($cage->getCagePermission())){
			$this->attemptToBuy($player, $cage);

			return;
		}

		$this->cagesSet[$player->getName()] = $cage;

		$player->sendMessage(TranslationContainer::getTranslation($player, 'cage-chosen', ["{CAGE_NAME}" => $cage->getCageName()]));
	}

	public function attemptToBuy(Player $player, Cage $cage): void{
		SkyWarsDatabase::getPlayerEntry($player, function(?PlayerData $pd) use ($player, $cage){
			if($player->hasPermission($cage->getCagePermission())){
				$player->sendMessage(TranslationContainer::getTranslation($player, 'cage-error-chosen'));

				return;
			}

			$economy = SkyWarsPE::getInstance()->getEconomy();
			$price = $cage->getPrice();
			if($economy === null){
				$player->sendMessage(TranslationContainer::getTranslation($player, 'economy-error'));
			}elseif($economy->myMoney($player) < $price){
				$player->sendMessage(TranslationContainer::getTranslation($player, 'economy-no-money'));
			}elseif($economy->reduceMoney($player->getName(), $price) !== EconomyAPI::RET_SUCCESS){
				$player->sendMessage(TranslationContainer::getTranslation($player, 'economy-internal'));
			}else{
				PluginPermission::getInstance()->addPermission($player, $cage->getCagePermission());

				$pd->permissions[] = $cage->getCagePermission();
				SkyWarsDatabase::setPlayerData($player, $pd);

				$this->setPlayerCage($player, $cage);
			}
		});
	}

	/**
	 * Get the list of cages, this will return all cages available in the
	 * array list.
	 *
	 * @return Cage[]
	 */
	public function getCages(): array{
		return $this->cage;
	}

	/**
	 * Returns cage object for the player, if there is no cages set
	 * default one will be used instead.
	 *
	 * @param Player $p
	 * @return Cage
	 */
	public function getPlayerCage(Player $p): Cage{
		return isset($this->cagesSet[$p->getName()]) ? $this->cagesSet[$p->getName()] : $this->defaultCage;
	}
}