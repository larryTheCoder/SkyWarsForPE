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


namespace larryTheCoder\commands;

use larryTheCoder\arena\api\Arena;
use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\EventListener;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Settings;
use larryTheCoder\utils\Utils;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;
use pocketmine\Server;

final class SkyWarsCommand {

	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(SkyWarsPE $e){
		$this->plugin = $e;
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $cmd
	 * @param string[] $args
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $cmd, array $args): bool{
		if(strtolower($cmd->getName()) === "sw" && isset($args[0])){
			switch(strtolower($args[0])){
				case "test":
					if(!$sender instanceof Player){
						$this->consoleSender($sender);

						break;
					}

					Utils::addSound([$sender], $args[1]);
					break;
				case "lobby":
				case "leave":
					if(!$sender->hasPermission('sw.command.lobby')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));

						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);

						break;
					}
					$pManager = $this->plugin->getArenaManager();
					$arena = $pManager->getPlayerArena($sender);
					if($arena === null){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'use-in-arena'));

						break;
					}

					if($arena->getPlayerManager()->isInArena($sender)){
						$arena->leaveArena($sender);
					}else{
						$sender->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
					}

					break;
				case "help":
					if(!$sender->hasPermission("sw.command.help")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
						break;
					}

					$sender->sendMessage("§9--- §c§lSkyWars help§l§9 ---§r§f");
					if($sender->hasPermission("sw.command.lobby")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'lobby-help'));
					}
					if($sender->hasPermission("sw.command.random")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'random-help'));
					}
					if($sender->hasPermission("sw.command.stats")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'stats-help'));
					}
					if($sender->hasPermission("sw.command.create")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'create-help'));
					}
					if($sender->hasPermission("sw.command.set")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'settings-help'));
					}
					if($sender->hasPermission("sw.command.join")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'join-help'));
					}
					if($sender->hasPermission("sw.command.setlobby")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'setlobby-help'));
					}
					if($sender->hasPermission("sw.command.npc")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'npc-help'));
					}
					if($sender->hasPermission("sw.command.cage")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'cage-help'));
					}

					$sender->sendMessage(TranslationContainer::getTranslation($sender, 'about-help'));
					break;
				case "create":
					if(!$sender->hasPermission('sw.command.create')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->getPanel()->setupArena($sender);
					}

					break;
				case "forcestart":
				case "start":
				case "fs":
					$manager = $this->plugin->getArenaManager();
					if(!$sender->hasPermission('sw.command.forcestart')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
						break;
					}elseif(!$sender instanceof Player){
						if(isset($args[1])){
							$arena = $manager->getArena($args[1]);
						}else{
							$sender->sendMessage(TranslationContainer::getTranslation($sender, 'start-usage'));
							break;
						}
					}else{
						$arena = $manager->getPlayerArena($sender);
						if(isset($args[1])){
							$arena = $manager->getArena($args[1]);
						}elseif($arena === null){
							$sender->sendMessage(TranslationContainer::getTranslation($sender, 'start-usage'));
							break;
						}
					}

					if($arena === null){
						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-not-exist'));
						break;
					}elseif($arena->hasFlags(Arena::ARENA_OFFLINE_MODE)){
						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-hibernation'));
					}elseif($arena->getStatus() < ArenaState::STATE_ARENA_RUNNING){
						$arena->startArena();
						$arena->setStatus(ArenaState::STATE_ARENA_RUNNING);

						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-started', ["{ARENA}" => $arena->getMapName()]));
					}else{
						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-running'));
					}
					break;
				case "settings":
					if(!$sender->hasPermission('sw.command.set')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->getPanel()->showSettingPanel($sender);
					}
					break;
				case "moderation":
					if(!$sender->hasPermission('sw.moderation')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!isset($args[1])){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'moderation-usage'));
					}else{
						if(strtolower($args[1]) === "on"){
							$sender->sendMessage(TranslationContainer::getTranslation($sender, 'arena-moderation-on'));

							EventListener::$moderators[$sender->getName()] = true;
						}else{
							$sender->sendMessage(TranslationContainer::getTranslation($sender, 'arena-moderation-off'));

							EventListener::$moderators[$sender->getName()] = false;
						}
					}

					break;
				case "stats":
					if(!$sender->hasPermission("sw.command.stats")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, "no-permission"));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}elseif(isset($args[1])){
						$this->plugin->getPanel()->showStatsPanel($sender, $args[1]);
					}else{
						$this->plugin->getPanel()->showStatsPanel($sender, $sender);
					}

					break;
				case "cage":
				case "cages":
					if(!$sender->hasPermission("sw.command.cage")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->getPanel()->showChooseCage($sender);
					}
					break;
				case "npc":
					if(!$sender->hasPermission("sw.command.npc")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, "no-permission"));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->getPanel()->setupNPCCoordinates($sender);
					}
					break;
				case "random":
					if(!$sender->hasPermission("sw.command.random")){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$arena = $this->plugin->getArenaManager()->getAvailableArena();
						if(is_null($arena)){
							$sender->sendMessage(TranslationContainer::getTranslation($sender, "arena-unavailable"));
						}else{
							$arena->getQueueManager()->addQueue($sender);
						}
					}
					break;
				case "join":
					if(!$sender->hasPermission('sw.command.join')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}elseif(!isset($args[1]) || isset($args[2])){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'join-usage'));
					}else{
						$arena = $this->plugin->getArenaManager()->getArena($args[1]);

						if($arena === null){
							$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-not-exist'));
						}elseif($arena->getPlayerManager()->isInArena($sender)){
							$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-running'));
						}elseif($arena->hasFlags(Arena::ARENA_CRASHED)){
							$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-crashed'));
						}elseif($arena->hasFlags(ArenaImpl::ARENA_DISABLED) || $arena->hasFlags(ArenaImpl::ARENA_IN_SETUP_MODE)){
							$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'arena-disabled'));
						}else{
							$arena->getQueueManager()->addQueue($sender);
						}
					}
					break;
				case "setlobby":
					if(!$sender->hasPermission('sw.command.setlobby')){
						$sender->sendMessage(TranslationContainer::getTranslation($sender, 'no-permission'));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}elseif($this->plugin->getArenaManager()->getPlayerArena($sender) !== null){
						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'command-setlobby-inarena'));
					}else{
						SkyWarsDatabase::setLobby($sender->getPosition());

						$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'main-lobby-set'));
					}
					break;
				case "about":
					$ver = $this->plugin->getDescription()->getVersion();

					$sender->sendMessage("§aSkyWarsForPE, §dUnseen Stars, Seven Blue Stones §e(Rain World Reference).");
					$sender->sendMessage("§7This plugin is running SkyWarsForPE §6v" . $ver . "§7 by§b larryTheCoder!");
					$sender->sendMessage("§7Source-link: https://github.com/larryTheCoder/SkyWarsForPE");
					break;
				default:
					$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'help-main'));
					break;
			}
		}else{
			$sender->sendMessage(Settings::$prefix . TranslationContainer::getTranslation($sender, 'help-main'));
		}

		return true;
	}

	private function consoleSender(CommandSender $p): void{
		$p->sendMessage("Please run command only in-game");
	}

}
