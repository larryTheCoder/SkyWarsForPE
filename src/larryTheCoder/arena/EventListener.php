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

namespace larryTheCoder\arena;

use larryTheCoder\arena\api\impl\ArenaListener;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\logger\CombatEntry;
use larryTheCoder\arena\logger\CombatLogger;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EventListener extends ArenaListener {

	/** @var ArenaImpl */
	private $arena;
	/** @var CombatLogger */
	private $logger;

	public function __construct(ArenaImpl $arena){
		$this->arena = $arena;
		$this->logger = new CombatLogger();
	}

	public function resetEntry(): void{
		$this->logger->resetAll();
	}

	public function onPlayerExhaust(PlayerExhaustEvent $event): void{
		$pm = $this->arena->getPlayerManager();
		$player = $event->getPlayer();

		// Do not exhaust spectators.
		if($player instanceof Player && $pm->isSpectator($player)){
			$event->setCancelled();
		}
	}

	public function onPlayerQuitEvent(PlayerQuitEvent $event): void{
		$player = $event->getPlayer();

		$this->arena->leaveArena($player, true);
	}

	public function onPlayerHitEvent(EntityDamageEvent $event): void{
		/** @var Player $player */
		$player = $event->getEntity();
		$cause = $event->getCause();

		if($this->arena->hasFlags(ArenaImpl::ARENA_INVINCIBLE_PERIOD)){
			$event->setCancelled();

			return;
		}

		$pm = $this->arena->getPlayerManager();

		// In order to remove "death" loading screen. Immediate respawn.
		$health = $player->getHealth() - $event->getFinalDamage();
		if($health <= 0){
			$event->setCancelled();

			$entry = $this->logger->getEntry($player->getName(), $cause === EntityDamageEvent::CAUSE_VOID ? 8 : 3);
			if($entry !== null && $entry->attackFrom !== null){
				$pm->broadcastToPlayers(SkyWarsPE::getInstance()->getMsg($player, 'death-message', false), false, ["{PLAYER}", "{KILLER}"], [$player->getName(), $entry->attackFrom]);

				$pm->addKills($entry->attackFrom);
			}else{
				$pm->broadcastToPlayers(SkyWarsPE::getInstance()->getMsg($player, self::getDeathMessageById($event->getCause()), false), false, ["{PLAYER}"], [$player->getName()]);
			}

			$this->onPlayerDeath($player, $event->getCause());
		}else{
			if($event instanceof EntityDamageByEntityEvent && ($damager = $event->getDamager()) instanceof Player){
				/** @var Player $damager */
				$this->logger->addEntry(CombatEntry::fromEntry($player->getName(), $cause, $damager->getName()));
			}else{
				$this->logger->addEntry(CombatEntry::fromEntry($player->getName(), $cause));
			}
		}
	}

	public static function getDeathMessageById(int $id): string{
		switch($id){
			case EntityDamageEvent::CAUSE_VOID:
				return "death-message-void";
			case EntityDamageEvent::CAUSE_SUICIDE:
				return "death-message-suicide";
			case EntityDamageEvent::CAUSE_SUFFOCATION:
				return "death-message-suffocated";
			case EntityDamageEvent::CAUSE_FIRE:
				return "death-message-burned";
			case EntityDamageEvent::CAUSE_CONTACT:
				return "death-message-catused";
			case EntityDamageEvent::CAUSE_FALL:
				return "death-message-fall";
			case EntityDamageEvent::CAUSE_LAVA:
				return "death-message-toasted";
			case EntityDamageEvent::CAUSE_DROWNING:
				return "death-message-drowned";
			case EntityDamageEvent::CAUSE_STARVATION:
				return "death-message-nature";
			case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
			case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION:
				return "death-message-explode";
			case EntityDamageEvent::CAUSE_CUSTOM:
				return "death-message-magic";
		}

		return "death-message-suicide";
	}

	public function onPlayerDeath(Player $player, int $deathFrom = EntityDamageEvent::CAUSE_SUICIDE): void{
		Utils::strikeLightning($player);

		$dropItems = $deathFrom === EntityDamageEvent::CAUSE_LAVA || $deathFrom === EntityDamageEvent::CAUSE_VOID;

		if(!$dropItems){
			/** @var Item[] $items */
			$items = array_merge($player->getInventory()->getContents(), $player->getArmorInventory()->getContents());

			foreach($items as $item){
				$player->getLevel()->dropItem($player, $item);
			}
		}

		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();

		$this->arena->setSpectator($player);
	}

	public function onPlayerExecuteCommand(PlayerCommandPreprocessEvent $event): void{
		$player = $event->getPlayer();

		if(!in_array(strtolower($event->getMessage()), ['sw', 'skywars'], true)
			&& $this->arena->getStatus() === ArenaState::STATE_ARENA_RUNNING
			&& !$player->hasPermission("sw.command.bypass")){
			$player->sendMessage(TextFormat::RED . "You cannot execute any command while in game.");
		}
	}
}