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


namespace larryTheCoder\items;

use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\item\Item;
use pocketmine\Server;
use pocketmine\utils\{
	Config, MainLogger
};

class RandomChest {

	/** @var SkyWarsPE */
	private $plugin;
	/** @var ChestLevel[] */
	private $chestLevels;
	/** @var MainLogger */
	private $logger;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;
		$this->logger = $plugin->getServer()->getLogger();
		/** @noinspection PhpUnhandledExceptionInspection */
		$this->load();
	}

	/**
	 * @throws \Exception
	 */
	public function load(){
		$config = new Config($this->plugin->getDataFolder() . "chests.yml", Config::YAML);

		if($config->get("version") > 1){
			throw new \Exception("Future version in chests.yml!");
		}
		$config->set("version", 1);

		$this->chestLevels = [];
		/** @var ChestLevel[] $incompleteChestLevel */
		$incompleteChestLevel = [];
		$levelsSection = $config->get("levels");
		$itemsSection = $config->get("items");
		if($levelsSection === null || $itemsSection === null || empty($levelsSection) || empty($itemsSection)){
			$this->plugin->saveResource("chest.yml", true);
		}
		# Is null? WTF!
		if($levelsSection === null){
			$this->getLogger()->warning("Not loading chests.yml: no levels section found");

			return;
		}
		# Hmm someone modded this plugin
		if($itemsSection === null){
			$this->getLogger()->warning("Not loading chests.yml: no items section found");

			return;
		}

		# Null in here is rarely happens because the file will be saved in the directory
		# Before this class will detect it
		foreach(array_keys($levelsSection) as $key){
			$itemValue = $config->getNested("levels.$key.item-value");
			$chance = $config->getNested("levels.$key.chance");
			if(!is_int($itemValue)){
				throw new \Exception("Invalid chests.yml: level `" . $key . "` is missing item-value!");
			}
			if(!is_int($chance)){
				throw new \Exception("Invalid chests.yml: level `" . $key . "` is missing item-value!");
			}
			$incompleteChestLevel[$key] = new ChestLevel($key, $itemValue, $chance, []);
		}

		foreach(array_keys($itemsSection) as $key){
			if(!isset($incompleteChestLevel[$key])){
				$this->logger->warning("Invalid chests.yml: level `" . $key . "` has a section under items, but no section under levels! skipping");
				continue;
			}
			$chestLevel = $incompleteChestLevel[$key];
			unset($incompleteChestLevel[$key]);

			$condition = $config->getNested("items.$key");
			if(is_array($condition)){
				$itemList = [];
				foreach($condition as $split){
					$amount = 1;
					$meta = 0;
					if(isset($split['meta']) && is_int($split['meta'])){
						$meta = intval($split['meta']);
					}
					if(isset($split['amount']) && is_int($split['amount'])){
						$amount = intval($split['amount']);
					}
					if(is_string($split['type'])){
						$item = Utils::convertToItem($split['type']);
						$item->setDamage($meta);
					}else{
						$item = Item::get($split['type'], $meta);
					}
					$item->setCount($amount);
					$itemList[] = $item;
				}

				if(empty($itemList)){
					throw new \Exception("Invalid chests.yml: level `" . $key . "` items list is empty!");
				}
				$this->chestLevels[$key] = new ChestLevel($key, $chestLevel->itemValue, $chestLevel->chance, $itemList);
			}else{
				throw new \Exception("Invalid chests.yml: non-list thing in items: " . $itemsSection[$key]);
			}
		}
		Server::getInstance()->getLogger()->info($this->plugin->getPrefix() . "§aStarted the chest random");
	}

	/**
	 * @return \AttachableThreadedLogger|MainLogger
	 */
	public function getLogger(){
		return $this->plugin->getServer()->getLogger();
	}

	/**
	 * @param int $size
	 * @param int $chestLevel
	 * @param int $minValue
	 * @param int $maxValue
	 * @return Item[]
	 */
	public function getItems(int $size, int $chestLevel, int $minValue, int $maxValue): array{
		$this->getLogger()->debug("Filling with size: $size, level: $chestLevel, min: $minValue, max: $maxValue");
		$totalChance = 0;
		/** @var ChestLevel[] $acceptableLevels */
		$acceptableLevels = [];
		foreach($this->chestLevels as $level){
			if($level->itemValue >= $minValue && $level->itemValue <= $maxValue){
				$acceptableLevels[] = $level;
				$totalChance += $level->chance;
			}
		}
		if(empty($acceptableLevels)){
			$this->getLogger()->warning("Warning: No acceptable chest levels found when filling chest with minValue=$minValue, maxValue=$maxValue! Chest will be completely empty.");

			return [];
		}
		$e = count($acceptableLevels);
		$this->getLogger()->debug("[RandomChests] Found acceptable levels: $e");
		$totalValue = 0;
		$inventory = [];
		while($totalValue <= $chestLevel){
			/** @var ChestLevel $level */
			$level = null;
			$chanceIndex = rand(0, $totalChance);
			$accumulatedChance = 0;
			// This is a somewhat convoluted way to give each level the right probability of being picked.
			foreach($acceptableLevels as $testLevel){
				$accumulatedChance += $testLevel->chance;
				if($accumulatedChance > $chanceIndex){
					$level = $testLevel;
					break;
				}
			}

			if(!is_null($level)){
				$this->getLogger()->debug("[RandomChests] Choosing level $level");
				foreach($level->items as $item){
					$inventory[] = $item;
				}
				$totalValue += $level->itemValue;
			}
		}

		$result = [];
		if(count($inventory) > $size){
			for($i = 0; $i <= $size; $i++){
				$result[] = $inventory[$i];
			}
		}else{
			return $inventory;
		}

		return $inventory;
	}
}