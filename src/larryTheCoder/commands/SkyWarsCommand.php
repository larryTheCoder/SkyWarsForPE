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

use larryTheCoder\arena\api\ArenaState;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;
use pocketmine\utils\TextFormat;

final class SkyWarsCommand {

	private $plugin;

	public function __construct(SkyWarsPE $e){
		$this->plugin = $e;
	}

	public function onCommand(CommandSender $sender, Command $cmd, array $args): bool{
		if(strtolower($cmd->getName()) === "sw" && isset($args[0])){
			switch(strtolower($args[0])){
				case "lobby":
				case "leave":
					if(!$sender->hasPermission('sw.command.lobby')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));

						return true;
					}
				if(!$sender instanceof Player){
					$this->consoleSender($sender);

					return true;
				}
				$pManager = $this->plugin->getArenaManager();
				$arena = $pManager->getPlayerArena($sender);
				if($arena === null || !$pManager->insideArenaLevel($sender)){
					$sender->sendMessage('Please use this command in-arena');

					return true;
				}
				$arena->leaveArena($sender);

				return true;
				case "test":
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					Utils::sendDebug($sender->getLocation()->__toString());
					break;
				case "help":
					if(!$sender->hasPermission("sw.command.help")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}

					$sender->sendMessage("§9--- §c§lSkyWars help§l§9 ---§r§f");
					if($sender->hasPermission("sw.command.lobby")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'lobby-help', false));
					}
					if($sender->hasPermission("sw.command.random")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'random-help', false));
					}
					if($sender->hasPermission("sw.command.stats")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'stats-help', false));
					}
					if($sender->hasPermission("sw.command.reload")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'reload-help', false));
					}
					if($sender->hasPermission("sw.command.create")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'create-help', false));
					}
					if($sender->hasPermission("sw.command.start")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'start-help', false));
					}
					if($sender->hasPermission("sw.command.stop")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'stop-help', false));
					}
					if($sender->hasPermission("sw.command.set")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'settings-help', false));
					}
					if($sender->hasPermission("sw.command.join")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'join-help', false));
					}
					if($sender->hasPermission("sw.command.setlobby")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'setlobby-help', false));
					}
					if($sender->hasPermission("sw.command.npc")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'npc-help', false));
					}
					if($sender->hasPermission("sw.command.kit")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'kit-help', false));
					}
					if($sender->hasPermission("sw.command.cage")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'cage-help', false));
					}

					$sender->sendMessage($this->plugin->getMsg($sender, 'about-help', false));
					break;
				case "create":
					if(!$sender->hasPermission('sw.command.create')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					$this->plugin->panel->setupArena($sender);
					break;
				case "settings":
					if(!$sender->hasPermission('sw.command.set')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					$this->plugin->panel->showSettingPanel($sender);
					break;
				case "stats":
					if(!$sender->hasPermission("sw.command.stats")){
						$sender->sendMessage($this->plugin->getMsg($sender, "no-permission"));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					$this->plugin->panel->showStatsPanel($sender);
					break;
				case "cage":
					if(!$sender->hasPermission("sw.command.cage")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					$this->plugin->panel->showChooseCage($sender);
					break;
				case "npc":
					if(!$sender->hasPermission("sw.command.npc")){
						$sender->sendMessage($this->plugin->getMsg($sender, "no-permission"));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}

					$this->plugin->panel->showNPCConfiguration($sender);
					break;
				case "random":
					if(!$sender->hasPermission("sw.command.random")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}
					$arena = $this->plugin->getArenaManager()->getAvailableArena();
					if(is_null($arena)){
						$sender->sendMessage("§cNo available arena, please try again later");
						break;
					}
					$arena->joinArena($sender);
					break;
				case "reload":
					if(!$sender->hasPermission("sw.command.reload")){
						$sender->sendMessage($this->plugin->getMsg($sender, "no-permission"));
						break;
					}
					$sender->sendMessage($this->plugin->getMsg($sender, 'plugin-reloading'));

					// Reload the arenas...
					Utils::unLoadGame();
					$this->plugin->getArenaManager()->checkArenas();
					foreach($this->plugin->getArenaManager()->getArenas() as $arena){
						$arena->resetArena();
					}

					$sender->sendMessage($this->plugin->getMsg($sender, 'plugin-reload'));
					break;
				case "start":
					if(!$sender->hasPermission('sw.command.start')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(isset($args[1])){
						if(!$this->plugin->getArenaManager()->arenaExist($args[1])){
							$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-exist'));
							break;
						}
						$this->plugin->getArenaManager()->getArena($args[1])->startGame();
						$sender->sendMessage(str_replace("{ARENA}", $args[1], $this->plugin->getMsg($sender, 'arena-started')));
						break;
					}
					if(!$sender instanceof Player){
						$sender->sendMessage($this->plugin->getMsg($sender, 'start-usage'));
						break;
					}
					if($this->plugin->getArenaManager()->getPlayerArena($sender) === null){
						$sender->sendMessage($this->plugin->getMsg($sender, 'start-usage'));
						break;
					}
					$arena = $this->plugin->getArenaManager()->getPlayerArena($sender);
					$arena->startGame();
					$sender->sendMessage(str_replace("{ARENA}", $arena->getArenaName(), $this->plugin->getMsg($sender, 'arena-started')));
					break;
				case "stop":
					if(!$sender->hasPermission('sw.command.stop')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(isset($args[1])){
						if(!$this->plugin->getArenaManager()->arenaExist($args[1])){
							$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-exist'));
							break;
						}
						if($this->plugin->getArenaManager()->getArena($args[1])->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
							$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-running'));
							break;
						}
						$this->plugin->getArenaManager()->getArena($args[1])->stopGame();
						break;
					}
					if(!$sender instanceof Player){
						$sender->sendMessage($this->plugin->getMsg($sender, 'stop-usage'));
						break;
					}
					if($this->plugin->getArenaManager()->getPlayerArena($sender)->getStatus() !== ArenaState::STATE_ARENA_RUNNING){
						$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-running'));
						break;
					}
					if($this->plugin->getArenaManager()->getPlayerArena($sender) === null){
						$sender->sendMessage($this->plugin->getMsg($sender, 'stop-usage'));
						break;
					}
					$this->plugin->getArenaManager()->getPlayerArena($sender)->stopGame();
					break;
				case "join":
					if(!$sender->hasPermission('sw.command.join')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}
					if(!isset($args[1]) || isset($args[2])){
						$sender->sendMessage($this->plugin->getMsg($sender, 'join-usage'));
						break;
					}
					if(!$this->plugin->getArenaManager()->arenaExist($args[1])){
						$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-exist'));
						break;
					}
					if($this->plugin->getArenaManager()->getArena($args[1])->isInArena($sender)){
						$sender->sendMessage($this->plugin->getMsg($sender, 'arena-running'));
						break;
					}
					$this->plugin->getArenaManager()->getArena($args[1])->joinArena($sender);
					break;
				case "setlobby":
					if(!$sender->hasPermission('sw.command.setlobby')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);
						break;
					}
					if($this->plugin->getArenaManager()->insideArenaLevel($sender)){
						$sender->sendMessage(TextFormat::RED . "You cannot run this command in an arena!");
						break;
					}

					$this->plugin->getDatabase()->setLobby($sender->getPosition());
					$sender->sendMessage($this->plugin->getMsg($sender, 'main-lobby-set'));
					break;
				case "about":
					$ver = $this->plugin->getDescription()->getVersion();
					$sender->sendMessage("§aSkyWarsForPE, §cSeven Red Suns.");
					$sender->sendMessage("§7This plugin is running SkyWarsForPE §6v" . $ver . "§7 by§b larryTheCoder!");
					$sender->sendMessage("§7Source-link: https://github.com/larryTheCoder/SkyWarsForPE");
					break;
				default:
					$sender->sendMessage($this->plugin->getMsg($sender, 'help-main'));
					break;
			}
		}else{
			$sender->sendMessage($this->plugin->getMsg($sender, 'help-main'));
		}

		return true;
	}

	private function consoleSender(CommandSender $p){
		$p->sendMessage("run command only in-game");
	}

}
