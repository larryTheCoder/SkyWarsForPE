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

namespace larryTheCoder\utils\permission;

use larryTheCoder\arena\api\utils\SingletonTrait;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\PlayerData;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\permission\PermissionAttachment;
use pocketmine\Player;
use pocketmine\utils\AssumptionFailedError;

class PluginPermission implements Listener {
	use SingletonTrait;

	/** @var PermissionAttachment[] */
	private $attachments;

	public function __construct(){
		$this->attachments = [];
	}

	public function addPermission(Player $player, string $permission): void{
		$attachment = $this->attachments[$player->getName()] ?? null;
		if($attachment === null){
			throw new AssumptionFailedError("Player permission attachment should be initialized in the first place.");
		}

		$attachment->setPermission($permission, true);
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerJoinEvent(PlayerJoinEvent $event): void{
		$player = $event->getPlayer();

		$this->attachments[$player->getName()] = $player->addAttachment(SkyWarsPE::getInstance());

		SkyWarsDatabase::getPlayerEntry($player, function(?PlayerData $result) use ($player){
			if(!isset($this->attachments[$player->getName()]) || $result === null) return;

			foreach($result->permissions as $permission){
				$this->attachments[$player->getName()]->setPermission($permission, true);
			}
		});
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerQuitEvent(PlayerQuitEvent $event): void{
		unset($this->attachments[$event->getPlayer()->getName()]);
	}
}