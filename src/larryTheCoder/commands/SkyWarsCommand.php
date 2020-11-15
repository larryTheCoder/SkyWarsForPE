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
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\Utils;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

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
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));

						break;
					}
					if(!$sender instanceof Player){
						$this->consoleSender($sender);

						break;
					}
					$pManager = $this->plugin->getArenaManager();
					$arena = $pManager->getPlayerArena($sender);
					if($arena === null){
						$sender->sendMessage('Please use this command in-arena');

						break;
					}

					if($arena->getPlayerManager()->isInArena($sender)){
						$arena->leaveArena($sender);
					} else {
						$sender->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
					}

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
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->panel->setupArena($sender);
					}

					break;
				case "settings":
					if(!$sender->hasPermission('sw.command.set')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->panel->showSettingPanel($sender);
					}
					break;
				case "stats":
					if(!$sender->hasPermission("sw.command.stats")){
						$sender->sendMessage($this->plugin->getMsg($sender, "no-permission"));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->panel->showStatsPanel($sender);
					}

					break;
				case "cage":
					if(!$sender->hasPermission("sw.command.cage")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->panel->setupNPCCoordinates($sender);
					}
					break;
				case "npc":
					if(!$sender->hasPermission("sw.command.npc")){
						$sender->sendMessage($this->plugin->getMsg($sender, "no-permission"));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$this->plugin->panel->setupNPCCoordinates($sender);
					}
					break;
				case "random":
					if(!$sender->hasPermission("sw.command.random")){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}else{
						$arena = $this->plugin->getArenaManager()->getAvailableArena();
						if(is_null($arena)){
							$sender->sendMessage("§cNo available arena, please try again later");
						}else{
							$arena->getQueueManager()->addQueue($sender);
						}
					}
					break;
				case "join":
					if(!$sender->hasPermission('sw.command.join')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}elseif(!isset($args[1]) || isset($args[2])){
						$sender->sendMessage($this->plugin->getMsg($sender, 'join-usage'));
					}else{
						$arena = $this->plugin->getArenaManager()->getArena($args[1]);

						if($arena === null){
							$sender->sendMessage($this->plugin->getMsg($sender, 'arena-not-exist'));
						}elseif($arena->getPlayerManager()->isInArena($sender)){
							$sender->sendMessage($this->plugin->getMsg($sender, 'arena-running'));
						}elseif($arena->hasFlags(Arena::ARENA_CRASHED)){
							$sender->sendMessage(TextFormat::RED . "The arena has crashed! Ask server owner to check server logs.");
						}elseif($arena->hasFlags(ArenaImpl::ARENA_DISABLED) || $arena->hasFlags(ArenaImpl::ARENA_IN_SETUP_MODE)){
							$sender->sendMessage(TextFormat::RED . "The arena is temporarily disabled, try again later.");
						}else{
							$arena->getQueueManager()->addQueue($sender);
						}
					}
					break;
				case "setlobby":
					if(!$sender->hasPermission('sw.command.setlobby')){
						$sender->sendMessage($this->plugin->getMsg($sender, 'no-permission', false));
					}elseif(!$sender instanceof Player){
						$this->consoleSender($sender);
					}elseif($this->plugin->getArenaManager()->getPlayerArena($sender) !== null){
						$sender->sendMessage(TextFormat::RED . "You cannot run this command in an arena!");
					}else{
						$this->plugin->getDatabase()->setLobby($sender->getPosition());

						$sender->sendMessage($this->plugin->getMsg($sender, 'main-lobby-set'));
					}
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

	private function consoleSender(CommandSender $p): void{
		$p->sendMessage("run command only in-game");
	}

}
