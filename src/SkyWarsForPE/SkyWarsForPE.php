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

namespace SkyWarsForPE;

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
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
    


class SkyWarsForPE extends PluginBase implements Listener{
    
    Public $economy;
    public $cfg;
    public $skywarsstarted = false;
    private $points;
    public $inv = [];

    public function onLoad() {
        $this->initConfig();
        $this->getLogger()->info(TextFormat::YELLOW.Messages::getMsg("plugin_load"));        
    }
    
    public function onEnable() {
        $this->initConfig();
	$this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->points = new Config($this->getDataFolder()."points.yml", Config::YAML);
        if(!$this->getServer()->isLevelGenerated($this->cfg->getNested('lobby.world'))){
            $this->getServer()->generateLevel($this->cfg->getNested('lobby.world'));
            
        $this->getLogger()->info(TextFormat::BLUE.Messages::getMsg("plugin_enabled"));
        $this->getLogger()->info(TextFormat::RED.Message::getMsg("Beta_Build"));
        }
        
    }
    
    public function onDisable() {
        $this->getLogger()->info(TextFormat::RED.Message::getMsg("plugin_disabled"));
        $this->getConfig()->save();
        $this->points()->save();
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
            $this->getServer()->getLogger()->info("Selected language English");
        }
        else{
            $this->msg = new Config($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->cfg->get('Language')}");//"Selected language {$this->cfg->get('Language')}");
        }
    }
    
    public function onCommand(CommandSender $sender ,Command $cmd, $label, array $args) {
        if(strtolower($cmd->getName()) == "sw"){
                if(isset($args[0])){
                    if($sender instanceof Player){
                    switch(strtolower($args[0])){
                        case "lobby":
                            if($sender->hasPermission('skywars.command.lobby')or $sender->hasPermission("SkyWars.command.lobby")){
                                $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if($sender->getLevel()->getName() == $this->getConfig()->get('aworld')){
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
        				}
                                        else{
        					$p->sendMessage("The match has finished, thanks for watching.");
        					$p->teleport($this->getServer()->getLevelByName($this->getConfig()->get('lobby'))->getSafeSpawn());
        					$p->setGameMode(0);
        					}
        					$this->stopGame($this->getConfig()->get('aworld')); //stop the game
        					}
        				}
				return true;                                    
                                }
                                $sender->teleport(new Position($this->cfg->getNested('lobby.x'), $this->cfg->getNested('lobby.y'), $this->cfg->getNested('lobby.z'), $this->getServer()->getLevelByName($this->cfg->getNested('lobby.world'))));
                                $sender->sendMessage($this->getPrefix().$this->getMsg('send_to_main_world'));
                                break;
                            case "play":
                                if(!$sender->hasPermission('skywars.command.play') or $sender->hasPermission('SkyWars.command')){
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                    break;
                                }

                                	if($this->aplayers >= $this->getConfig()->get('neededplayers') and $this->skywarsstarted == false){ //if players in the world are more or equal as the max players
					$sender->sendMessage($this->getPrefix().$this->getMsg('game_full')); // game full
                                        
				return true;
                                
				}elseif
                                    ($this->aplayers < $this->getConfig()->get('neededplayers') and $this->skywarsstarted == false){ //if player number is less than the max.
					$spawn = $this->getConfig()->get('spawns')[$this->aplayers]; //no need to do + 1 on this, because arrays start counting form 0 // get the correct spawn place
					$sender->teleport($this->cfg->getNested('lobby.x'), $this->cfg->getNested('lobby.y'), $this->cfg->getNested('lobby.z'), $this->getServer()->getLevelByName($this->getConfig()->get('aworld'))); //teleport to it
					$this->aplayers = $this->aplayers + 1; //then add a player to the array
                                        $sender->sendMessage($this->getPrefix().$this->getMsg('send_to_main_world'));
      				if($this->aplayers == $this->getConfig()->get('neededplayers')){ //if the players became the same as neededplayers
      						$this->startGame($this->getConfig()->get('aworld')); //start the game
      				}
      		            }
                                
                            case "stat":
                                if($sender->hasPermission("skywars.command.stat") or $sender->hasPermission("skywars.command") or $sender->hasPermission("skywars")){
                                   $sender->sendMessage($this->getPrefix().$this->getMsg('has_no_permission'));
                                   break;
                                }
                                if(!(isset($args[1]))){
                                        $player = $sender->getName();
				        $deaths = $this->points->get($player)[0];
				        $kills = $this->points->get($player)[1];
					$points = $this->points->get($player)[2];
					$sender->sendMessage($this->getPrefix().$this->getMsg('game_stat1'));
                                        //("§b$player.,§ahave§9".$deaths." §adeaths,§9".$kills."§akills and§9".$points." §apoints!");
				return true;
                                    }
                                    else
                                    {    
                                        $player = $args[1];
					$deaths = $this->points->get($player)[0];
					$kills = $this->points->get($player)[1];
					$points = $this->points->get($player)[2];
					$sender->sendMessage($this->getPrefix().$this->getMsg('game_stat2'));
                                                //($player." has ".$deaths." deaths, ".$kills." kills and ".$points." points.");
					return true;
                                    }
                                
                            case "setlobby":
                                if(!$sender->hasPermission('skywars.command.setlobby')){
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(isset($args[1])){
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('setlobby_help'));
                                    break;
                                }
                                $this->setters[strtolower($sender->getName())]['type'] = "mainlobby";
                                $sender->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                                break;
                            default:
                                $sender->sendMessage($this->getPrefix().$this->getMsg('help'));
                            }
                            return;
                        }
                        $sender->sendMessage('run command only in-game');
                        return;
                        }
                        $sender->sendMessage($this->getPrefix().$this->getMsg('help'));
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
				$event->setDeathMessage()->getLogger()->info($this->getPrefix().$this->getMsg('player_killed1'));
                        
	}else{
			$event->setDeathMessage()->getLogger()->info($this->getPrefix().$this->getMsg('player_killed2'));//($victim."[".$this->getConfig()->get($victim[2])."] died.");
			}
        	if($this->aplayers <= 1){ //if only 1 player is left
        		foreach($this->getServer()->getLevelByName($this->getConfig()->get('aworld'))->getPlayers() as $p){ //detects the winner
        			if($p->getGameMode() == 0){
        				$p->sendMessage($this->getPrefix().$this->getMsg('win_game'));
        				$p->sendMessage("The game has finished, you will be teleported to the lobby.");
        				$p->teleport($this->getServer()->getLevelByName($this->getConfig()->get('lobby'))->getSafeSpawn()); //teleport to the lobby
        				$points = $this->points->get($p)[2] + $this->config->get('points-per-match'); //get points and add
        				$deaths = $this->points->get($player)[0]; //get the victim's deaths, add one and store in a variable
       					$kills = $this->points->get($player)[1]; //get the players kills and store in a var
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
        $user = $event->getPlayer->getName();
        if($this->config->get('chat-format') == true){
        	$event->setFormat("&c[&b".$this->points->get($user[2])."$&c]&a".$user."&7&l>&f".$event->getMessage());
        }
    }
        
  
    
        
    public function loadInvs(){
        foreach($this->getServer()->getOnlinePlayers() as $p){
            if(isset($this->inv[strtolower($p->getName())])){
                foreach($this->inv as $slot => $i){
                    list($id, $dmg, $count) = explode(":", $i);
                    $item = Item::get($id, $dmg, $count);
                    $p->getInventory()->setItem($slot, $item);
                    unset($this->plugin->inv[strtolower($p->getName())]);
                }
            }
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
        
    public function registerEconomy(){
        $economy = ["EconomyAPI", "PocketMoney", "MassiveEconomy", "GoldStd"];
        foreach($economy as $plugin){
            $ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
            if($ins instanceof Plugin && $ins->isEnabled()){
                $this->economy = $ins;
                $this->getServer()->getLogger()->info($this->getPrefix().$this->getMsg('PLUGIN S'));//TO-DO Selected economy plugin: $plugin
                return;
            }
        }
        $this->economy = null;
    }
}
