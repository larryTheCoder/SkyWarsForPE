<?php

namespace SkyWarsForPE\Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use SkyWarsForPE\SkyWarsForPE;

class Commands extends BaseCommand {

       public $plugin;
       private $points = [];
       private $skywarsstarted = false;

    public function __construct(SkyWarsForPE $plugin){
      $this->plugin = $plugin;
      parent::__construct($plugin, "SkyWarsForPE", "Main skywars command", "/SkyWarsForPE", "/SkyWarsForPE", ["sw"]);
    }

    public function onCommand(CommandSender $Command ,Command $cmd, $label, array $args) {
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
}