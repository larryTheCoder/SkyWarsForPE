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

namespace larryTheCoder\libs\kits;


use pocketmine\item\Item;
use pocketmine\Player;

class NormalKits extends KitsAPI {

	/** @var string */
	private $description;
	/** @var string */
	private $name;
	/** @var int */
	private $price;
	/** @var Item[] */
	private $item;
	/** @var Item[] */
	private $armourContent;

	public function __construct(string $name, int $price, string $description){
		$this->name = $name;
		$this->price = $price;
		$this->description = $description;
	}

	/**
	 * Set the inventory item
	 *
	 * @param Item[] $item
	 */
	public function setInventoryItem(array $item){
		$this->item = $item;
	}

	/**
	 * Set the armour inventory content
	 *
	 * @param Item[] $item
	 */
	public function setArmourItem(array $item){
		$this->armourContent = $item;
	}

	/**
	 * Get the kit name for the Kit
	 * @return string
	 */
	public function getKitName(): string{
		return $this->name;
	}

	/**
	 * The price for the kits, depends on the
	 * server if they installed any Economy
	 * plugins
	 *
	 * @return int
	 */
	public function getKitPrice(): int{
		return $this->price;
	}

	/**
	 * Get the description for the Kit
	 * put 'null' if you don't want them
	 *
	 * @return string
	 */
	public function getDescription(): string{
		return $this->description;
	}

	/**
	 * Provide to execute this kit/feature. This
	 * kit will be executed when the game has been started.
	 *
	 * @param Player $p
	 */
	public function executeKit(Player $p){
		$p->getInventory()->setContents($this->item);
		$p->getArmorInventory()->setHelmet($this->armourContent[0]);
		$p->getArmorInventory()->setChestplate($this->armourContent[1]);
		$p->getArmorInventory()->setLeggings($this->armourContent[2]);
		$p->getArmorInventory()->setBoots($this->armourContent[3]);
	}

	/**
	 * @return Item[]
	 */
	public function getItems(): array{
		return $this->item;
	}

	/**
	 * @return Item[]
	 */
	public function getArmourContents(): array{

		return $this->armourContent;
	}
}