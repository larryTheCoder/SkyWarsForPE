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

namespace larryTheCoder;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\listener\BasicListener;
use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\database\SkyWarsDatabase;
use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;

class EventListener extends BasicListener implements Listener {

	/** @var bool */
	public static $isDebug = false;
	/** @var bool[] */
	public static $moderators = [];

	/** @var int */
	private static $nextPlayer = 0;

	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;

		// My project environment.
		self::$isDebug = getenv("Project") === "E:\ACD-HyruleServer\plugins";
	}

	/**
	 * @param PlayerJoinEvent $e
	 *
	 * @priority MONITOR
	 */
	public function onPlayerLogin(PlayerJoinEvent $e): void{
		SkyWarsDatabase::createPlayer($e->getPlayer());
	}

	public function loginEvent(DataPacketReceiveEvent $event): void{
		// DEBUGGING PURPOSES

		if(!self::$isDebug) return;

		$packet = $event->getPacket();

		if($packet instanceof LoginPacket){
			$packet->username = "larryZ00" . self::$nextPlayer++;
			$packet->clientUUID = UUID::fromRandom()->toString();
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority HIGHEST
	 */
	public function playerJoinEvent(PlayerJoinEvent $event): void{
		$player = $event->getPlayer();

		if($player->hasPermission("sw.moderation") && !isset($this->moderators[$player->getName()])){
			$player->sendMessage(TranslationContainer::getTranslation($player, 'moderation-pre-enable'));

			self::$moderators[$player->getName()] = true;
		}
	}

	/**
	 * @param PlayerChatEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerChatEvent(PlayerChatEvent $event): void{
		parent::onPlayerChatEvent($event);

		// Filter player messages from getting sent to players in arena.
		$player = $event->getPlayer();
		if(($arena = $this->getArena($player)) === null){
			$filterRecipients = [];
			foreach($event->getRecipients() as $sender){
				if(!($sender instanceof Player)){
					continue;
				}

				if($this->getArena($sender) === null){
					$filterRecipients[] = $sender;
				}
			}

			$event->setRecipients($filterRecipients);
		}else{
			foreach(self::$moderators as $playerName => $indicator){
				$moderator = Server::getInstance()->getPlayer($playerName);

				if($indicator && $moderator !== null && $moderator->isOnline() && $playerName !== $player->getName()){
					$moderator->sendMessage(TextFormat::GRAY . $arena->getMapName() . " > " . $event->getFormat());
				}
			}
		}
	}

	public function getArena(Human $player): ?Arena{
		$arenas = $this->plugin->getArenaManager()->getArenas();
		foreach($arenas as $arena){
			if($arena->getPlayerManager()->isInArena($player)){
				return $arena;
			}
		}

		return null;
	}
}