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

declare(strict_types = 1);

namespace larryTheCoder\utils;

use AndreasHGK\EasyKits\Kit;
use AndreasHGK\EasyKits\manager\KitManager as EasyKit;
use AndreasHGK\EasyKits\utils\LangUtils;
use larryTheCoder\arena\api\translation\TranslationContainer as TC;
use larryTheCoder\forms\elements\Button;
use larryTheCoder\forms\FormQueue;
use larryTheCoder\forms\MenuForm;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

/**
 * Kit Manager for SkyWarsForPE using EasyKits plugin.
 *
 * <p> EasyKits plugin has no proper documentation on how to perform specific
 * kits over a player in an arena, most of these functions are hacks to access the kits
 * over a plugin and as in cooldown and permission, those two has been stripped out from the code.
 *
 * @package larryTheCoder\utils
 */
class KitManager implements Listener {

	/** @var Kit[] */
	private $selectedKits = [];

	/**
	 * @param Player $player
	 *
	 * @return Kit|null
	 */
	public function getKit(Player $player): ?Kit{
		return $this->selectedKits[$player->getName()] ?? null;
	}

	/**
	 * @param Player $player
	 */
	public function sendKits(Player $player): void{
		$kits = $this->getKits();

		$form = new MenuForm(TC::getTranslation($player, 'kit-selection-1'), TC::getTranslation($player, 'kit-selection-2'), [
			'None',
		], function(Player $player, Button $selected) use ($kits): void{
			$kit = $kits[$selected->getValue() - 1] ?? null;
			if($kit === null){
				$player->sendMessage(TC::getTranslation($player, 'kit-removed'));

				unset($this->selectedKits[$player->getName()]);
			}elseif($player->hasPermission($kit->getPermission())){
				$player->sendMessage(TC::getTranslation($player, 'kit-selected', ["{KIT_NAME}" => $kit->getName()]));

				$this->selectedKits[$player->getName()] = $kit;
			}else{
				$player->sendMessage(TC::getTranslation($player, 'kit-no-permission', ["{KIT_NAME}" => $kit->getName()]));
			}
		});

		$selectedKit = $this->selectedKits[$player->getName()] ?? null;
		foreach($kits as $kit){
			if($selectedKit !== null && $selectedKit->getName() === $kit->getName()){
				$form->append($kit->getName() . "\n" . TextFormat::GREEN . "Selected kit");
			}elseif($player->hasPermission($kit->getPermission())){
				$form->append($kit->getName());
			}else{
				$form->append(TextFormat::RED . $kit->getName());
			}
		}

		FormQueue::sendForm($player, $form);
	}

	/**
	 * @return Kit[]
	 */
	private function getKits(): array{
		$availKits = [];
		$kits = EasyKit::getAll();

		foreach($kits as $kit){
			// Filter a kit to use only a specific permission.
			// This is to ensure that the kit we are using are for SW game only.
			if(preg_match("#sw\.internal\.([A-Za-z0-9_]*)#", $kit->getPermission(), $matches) > 0){
				$availKits[] = $kit;
			}
		}

		return $availKits;
	}

	/**
	 * @param Player $player
	 */
	public function claimKit(Player $player): void{
		$kit = $this->getKit($player);
		if($kit === null) return;

		$armorSlots = $kit->getArmor();
		$playerArmorInv = $player->getArmorInventory();
		$playerArmor = $playerArmorInv->getContents(true);
		$playerInv = $player->getInventory();
		$invSlots = $kit->getItems();
		$invCount = count($invSlots);

		foreach($armorSlots as $key => $armorSlot){
			if($playerArmor[$key]->getId() !== Item::AIR){
				$invCount++;
			}
		}

		foreach($invSlots as $key => $invSlot){
			if($kit->doOverride()) $playerInv->setItem($key, $invSlot);
			else $playerInv->addItem($invSlot);
		}

		foreach($armorSlots as $key => $armorSlot){
			if($kit->doOverrideArmor()) $playerArmorInv->setItem($key, $armorSlot);
			elseif($playerArmorInv->getItem($key)->getId() !== Item::AIR) $playerInv->addItem($armorSlot);
			else $playerArmorInv->setItem($key, $armorSlot);
		}

		foreach($kit->getEffects() as $effect){
			$player->addEffect($effect);
		}

		foreach($kit->getCommands() as $command){
			Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), LangUtils::replaceVariables($command, ["{PLAYER}" => $player->getName(), "{NICK}" => $player->getDisplayName()]));
		}
	}
}