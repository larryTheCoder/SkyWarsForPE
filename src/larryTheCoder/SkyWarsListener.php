<?php

/*
 * SkyWarsForPE plugin for PocketMine-MP
 * Copyright (C) 2016 larrythecoder <https://github.com/larrythecoder/SkyWarsForPE>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

namespace larryTheCoder;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerKickEvent;
    


class SkyWarsListener extends PluginBase implements Listener{
    
private $economy;
private $cfg;
private $skywarsstarted = false;
private $points;
private $aplayers = 0;
//public $bplayers;
//public $cplayers;

  
    public function onEnable() {
        $this->initConfig();
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->points = new Config($this->getDataFolder()."points.yml", Config::YAML);
        
        if(!$this->getServer()->isLevelGenerated($this->cfg->getNested('lobby.world'))){ //TO-DO $this->getMsg
            $this->getServer()->generateLevel($this->cfg->getNested('lobby.world'));
        }
        $this->getServer()->getLogger()->info($this->getPrefix().$this->getMsg("§bPlugin Enabled"));
        $this->getServer()->getLogger()->info($this->getPrefix().$this->getMsg("§4This is beta build, its may contain bugs or crashes!"));
    
    }
    
    public function onDisable() {
        $this->getServer()->getLogger()->info($this->getPrefix().$this->getMsg("§c[§aSkyWars§c] §4Plugin disabled"));
        $this->getConfig()->save();
        $this->initConfig()->save();
    }
    
    public function initConfig(){
        if(!file_exists($this->getDataFolder())){
            @mkdir($this->getDataFolder());
        }
        if(!is_file($this->getDataFolder()."config.yml")){
            $this->saveResource("config.yml");
        }
        $this->cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
        if(!file_exists($this->getDataFolder()."languages/")){
            @mkdir($this->getDataFolder()."languages/");
        }
        if(!is_file($this->getDataFolder()."languages/English.yml")){
                $this->saveResource("languages/English.yml");
        }
        if(!is_file($this->getDataFolder()."languages/Czech.yml")){
                $this->saveResource("languages/Czech.yml");
        }
        if(!is_file($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml")){
            $this->msg = new Config($this->getDataFolder()."languages/English.yml", Config::YAML);
        }
        else{
            $this->msg = new Config($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info($this->getPrefix().("§aSelected language {$this->cfg->get('Language')}"));//"Selected language {$this->cfg->get('Language')}");
        }
    }
    public function onCommand(CommandSender $sender ,Command $cmd, $label, array $args) {
             if(strtolower($cmd->getName()) == "sw"){
                if(isset($args[0])){
                    if($sender instanceof Player){
                    switch(strtolower($args[0])){
                        case "help":
                                if(!$sender->hasPermission("sw.command.help")){
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                $msg = "§9--- §c§lSkyWars help§l§9 ---§r§f";
                                if($sender->hasPermission('sw.command.exit')) $msg .= $this->getMsg('lobby_help');
                                if($sender->hasPermission('sw.command.play')) $msg .= $this->getMsg('play_help');
                                if($sender->hasPermission('sw.command.spawnpos')) $msg .= $this->getMsg('spawnpos_help');
                                if($sender->hasPermission('sw.command.stat')) $msg .= $this->getMsg('stat_help');
                                $sender->sendMessage($msg);
                                break;
                        case "left":
				if(!$sender->hasPermission("sw.command.left") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                    break;
                                }
					if($sender->getLevel()->getName() == $this->getConfig()->get('aworld')){
						$playersleft = $this->getConfig()->get('neededplayers') - $this->aplayers;
						$sender->sendMessage("Players left untill the game begin: ".$playersleft);
					return true;
					}else{
						$sender->sendMessage("You are not in a SkyWars world.");
						return true;
					}
                        case "exit":
				if(!$sender->hasPermission("sw.command.exit") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                    break;
                                }                                    
					if($sender->getLevel()->getName() == $this->getConfig()->get('aworld')){ //if the level of the sender is a skywars one
						$this->aplayers = $this->aplayers - 1; //remove 1 to the array
						$sender->teleport($this->getServer()->getLevelByName($this->getConfig()>get('lobby'))->getSafeSpawn()); //teleport to the lobby
						$sender->sendMessage("You left the game.");
						if($this->aplayers <= 1){ //if only 1 player is left
        						foreach($this->getServer()->getLevelByName($this->getConfig()->get('aworld'))->getPlayers() as $p){ //detects the winner
        							if($p->getGameMode() == 0){
        								$p->sendMessage("You won the match!");
        								$p->sendMessage("The game has finished, you will be teleported to the lobby.");
        								$p->teleport($this->getServer()->getLevelByName($this->config->get('lobby'))->getSafeSpawn()); //teleport to the lobby
        								$points = $this->points->get($p)[2] + $this->getConfig()->get('points-per-match'); //get points and add
        								$deaths = $this->points->get($player)[0]; //get the victim's deaths, add one and store in a variable
       									$kills = $this->points->get($player)[1]; //get the players kills and store in a var
        								$this->getConfig()->set($p, array($deaths, $kills, $points));
        							}else{
        								$p->sendMessage("The match has finished, thanks for watching.");
        								$p->teleport($this->getServer()->getLevelByName($this->getConfig()->get('lobby'))->getSafeSpawn());
        								$p->setGameMode(0);
        						}
        						$this->stopGame($this->getConfig()->get('aworld')); //stop the game
        					}
        				}
						return true;
					}else{
						$sender->sendMessage("You are not in the SkyWars world.");
						return true;
					}
				
			    case "play":
				if(!$sender->hasPermission("sw.command.play") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                                    $sender->sendMessage($this->getMsg('has_no_permission'));
                                    break;
                                } 					
                                        if($this->aplayers >= $this->getConfig()->get('neededplayers') and $this->skywarsstarted == false){ //if players in the world are more or equal as the max players
						$sender->sendMessage("The game is full"); // game full
						return true;
					}elseif($this->aplayers < $this->getConfig()->get('neededplayers') and $this->skywarsstarted == false){ //if player number is less than the max.
						$spawn = $this->getConfig()->get('spawns')[$this->aplayers]; //no need to do + 1 on this, because arrays start counting form 0 // get the correct spawn place
						$sender->teleport(new Position($spawn[0], $spawn[1], $spawn[2], $this->getServer()->getLevelByName($this->getConfig()->get('aworld')))); //teleport to it
						$this->aplayers = $this->aplayers + 1; //then add a player to the array
						$sender->sendMessage("You have been teleported to the game world.");
      						if($this->aplayers == $this->getConfig()->get('neededplayers')){ //if the players became the same as neededplayers
      							$this->startGame($this->getConfig()>get('aworld')); //start the game
      						}
      						return true;
					}elseif($this->skywarsstarted == true){ //if the game is already started
                        			$sender->sendMessage("The game is already started");
                        			return true;
                        		}
                            case "spawnpos":
				if(!$sender->hasPermission("sw.command.spawnpos") or $sender->hasPermission("skywars.command") or $sender->hasPermission("skywars")){
                                   $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                   break;
                                }					
                                        $x = $sender->getX();
					$y = $sender->getY(); //get coordinates and store in variables
					$z = $sender->getZ();
					$this->getConfig()->set('spawns', array($x, $y, $z));
					$sender->sendMessage("Spawn position set to: ".$x.", ".$y.", ".$z.", level: ".$sender->getLevel()->getName());
					return true;
                                        
                            case "stat":
                                if(!$sender->hasPermission("sw.command.stat") or $sender->hasPermission("skywars.command") or $sender->hasPermission("skywars")){
                                   $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                   break;
                                }
                                        if(!(isset($args[1]))){
                                        	$player = $sender->getName();
						$deaths = $this->points->get($player)[0];
						$kills = $this->points->get($player)[1];
						$points = $this->points->get($player)[2];
						$sender->sendMessage("You have ".$deaths." deaths, ".$kills." kills and ".$points." points.");
						return true;
                                        }else{
                                        	$player = $args[1];
						$deaths = $this->points->get($player)[0];
						$kills = $this->points->get($player)[1];
						$points = $this->points->get($player)[2];
						$sender->sendMessage($player." has ".$deaths." deaths, ".$kills." kills and ".$points." points.");
						return true;
                                        }
                            }
                            return;
                        }
                        $sender->sendMessage($this->getPrefix().$this->getMsg('use_cmd_in_game'));
                        return;
                        }
                        $sender->sendMessage($this->getPrefix().$this->getMsg('SkyWars_help'));
                }
        }
    
 
    public function onHurt(EntityDamageEvent $event){
        if(!($event instanceof EntityDamageByEntityEvent) or !($event->getDamager() instanceof Player)) return;
        if($event->getEntity()->getLevel()->getName() == $this->getConfig()->get('lobby')){
        	$event->setCancelled(true); //disable pvp in the lobby
        	$event->getDamager()->sendMessage($this->getPrefix().$this->getMsg('lobby_hurt'));           
        }
    }
    public  function onDeath(PlayerDeathEvent $event){
        if($event->getEntity()->getLevel()->getName() == $this->getConfig()->get('aworld')){ 
        	$this->aplayers = $this->aplayers -1;
        	$victim = $event->getEntity()->getName();
        	$this->addDeath($victim);
        	$cause = $event->getEntity()->getLastDamageCause();
        	if($cause instanceof EntityDamageByEntityEvent){
			$killer = $cause->getDamager();
			if($killer instanceof Player){
				$this->addKill($killer->getName());
				$event->setDeathMessage()->getLogger()->info("§a".$victim."[".$this->getConfig()->get($victim[2])."] died.");
                        
	}else{
			$event->setDeathMessage()->getLogger()->info($this->getPrefix().$this->getMsg('player_killed2'));//($victim."[".$this->getConfig()->get($victim[2])."] died.");
			}
        	if($this->aplayers <= 1){ //if only 1 player is left
        		foreach($this->getServer()->getLevelByName($this->getConfig()->get('aworld'))->getPlayers() as $p){ //detects the winner
        			if($p->getGameMode() == 0){
        				$p->sendMessage($this->getPrefix().$this->getMsg('win_game'));
        				$p->sendMessage($this->getPrefix().$this->getMsg("The game has finished, you will be teleported to the lobby."));
        				$p->teleport($this->getServer()->getLevelByName($this->getConfig()->get('lobby'))->getSafeSpawn()); //teleport to the lobby
        				$points = $this->points->get($p)[2] + $this->config->get('points-per-match'); //get points and add
        				$deaths = $this->points->get($p)[0]; //get the victim's deaths, add one and store in a variable
       					$kills = $this->points->get($p)[1]; //get the players kills and store in a var
        				$this->config->set($p, array($deaths, $kills, $points));
        		}else{
        				$p->sendMessage($this->getPrefix().$this->getMsg('game_full'));//TO-DO "The match hs finished, thanks for watching."
        				$p->teleport($this->getServer()->getLevelByName($this->config->get('lobby'))->getSafeSpawn());
        				$p->setGameMode(0);
        			}
        			$this->stopGame($this->config->get('aworld')); //stop the game
        			}
        		}
        	}
        }
    }
      
    public function onBlockBreak(BlockBreakEvent $event){
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('lobby') and !$event->getPlayer()->hasPermission("skywars.editlobby") || !$event->getPlayer()->hasPermission("skywars")){ //if level is lobby and player hasn't the permission to modify it
		$event->setCancelled(); // cancel the event
                $sender->sendMessage($this->getPrefix().$this->getMsg('lobby_break'));
	}
    }
	
    public function onBlockPlace(BlockPlaceEvent $event){
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('lobby') and !$event->getPlayer()->hasPermission("skywars.editlobby") || !$event->getPlayer()->hasPermission("skywars")){
		$event->setCancelled();
	}
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('aworld') and $event->getPlayer()->getGameMode() == 3){
		$event->setCancelled();
	}
    }
    
            
    public function onChat(PlayerChatEvent $event){
        $player = strtolower($event->getPlayer()->getName());
        if($this->getConfig()->get('chat-format') == true){
        	$event->setFormat("§c[§b...".$this->points->get($player[2])."...-§c]§a".$player."§7§l>§f§r".$event->getMessage());
        
        }else {
		$message = $event->getMessage();
		$player = $event->getPlayer()->getName();
		$event->setFormat("§c[§b0§c]§a".$player."§7§l>§f§r".$event->getMessage());
		}
	}
    
 

    public function onPlayerInteract(PlayerInteractEvent $event){
        //$this->onPlayerInteract2($event);
	$player = $event->getPlayer();
	$ID = $event->getBlock()->getId();
                if($ID == 323 or $ID == 63 or $ID == 68){
        		$tile = $event->getBlock()->getLevel()->getTile(new Vector3($event->getBlock()->getX(),$event->getBlock()->getY(),$event->getBlock()->getZ()));
        		if($tile instanceof Sign){
        			if($tile->gettext(0)=="[MiniGame]" and $tile->gettext(1)=="Skywars" and $tile->gettext(3) == $this->getConfig()->get('aworld')){
        				if($this->aplayers >= $this->getConfig()->get('neededplayers') and $this->skywarsstarted == false){ //if players in the world are more or equal as the max players
						$player->sendMessage($this->getPrefix().$this->getMsg('game_full')); // game full
					}elseif($this->aplayers < $this->config->get('neededplayers') and $this->skywarsstarted == false){ //if player number is less than the max.
						$spawn = $this->getConfig()->get('spawns')[$this->aplayers]; //no need to do + 1 on this, because arrays start counting form 0 // get the correct spawn place
						$player->teleport(new Position($spawn[0], $spawn[1], $spawn[2], $this->getServer()->getLevelByName($this->config->get('aworld')))); 
						$this->aplayers = $this->aplayers + 1; //then add a player to the array
						$player->sendMessage($this->getPrefix().$this->getMsg('send_game'));
      						if($this->aplayers == $this->getConfig()->get('neededplayers')){ 
      							$this->startGame($this->getConfig()->get('aworld')); 
      						}
					}elseif($this->skywarsstarted == true){ //if the game is already started
                        			$player->sendMessage($this->getPrefix().$this->getMsg('game_started'));
        				}
        			}	
        		}
        	}
	}
    public function kickPlayer($p, $reason = ""){
        $players = array_merge($this->aworld, $this->lobby);
            $players[strtolower($p)]->sendMessage(str_replace("%1", $reason, $this->plugin->getMsg('kick_from_game')));
            $this->leaveArena($players[strtolower($p)]);
    }
   
        
    public function registerEconomy(){
        $economy = ["EconomyAPI", "PocketMoney", "MassiveEconomy", "GoldStd"];
        foreach($economy as $plugin){
            $ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
            if($ins instanceof Plugin && $ins->isEnabled()){
                $this->economy = $ins;
                $this->getServer()->getLogger()->info("Selected economy plugin: $plugin");
                return;
            }
        }
        $this->economy = null;
    }
    
    public function getMsg($key){
        $msg = $this->msg;
        return str_replace("&", "§", $msg->get($key));
    }
     
    public function getPrefix(){
        return str_replace("&", "§", $this->cfg->get('Prefix'));
    }
     

}