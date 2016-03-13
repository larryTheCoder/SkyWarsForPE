<?php

namespace larryTheCoder\Command;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\level\Position;
use pocketmine\Player;
use larryTheCoder\SkyWarsAPI;


class SkyWarsCommand{

    public function __construct(SkyWarsAPI $plugin){
        $this->plugin = $plugin;
    }
    
    public function onCommand(CommandSender $p, Command $cmd, $label, array $args) {
    switch($cmd->getName()){
                case "lobby":
                        if(!$p->hasPermission('sw.command.lobby')){
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                        break;
                    }
		if(!($p instanceof Player)){
			$p->sendMessage("Please run this command in-game");

			return false;
		}
                        $p->teleport(new Position($this->plugin->config->getNested('lobby.x'), $this->plugin->config->getNested('lobby.y'), $this->plugin->config->getNested('lobby.z'), $this->plugin->getServer()->getLevelByName($this->plugin->config->getNested('lobby.world'))));
                        $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('send_to_main_world'));
                        break;
        }
        if(strtolower($cmd->getName()) == "skywars"){
            if(isset($args[0])){
                if($p instanceof Player){
                    switch(strtolower($args[0])){
                case "help":
                                if(!$p->hasPermission("sw.command.help")){
                                    $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                                    break;
                                }
                                $msg = "§9--- §c§lColorMatch help§l§9 ---§r§f";
                                if($p->hasPermission('sw.command.lobby')) $msg .= $this->plugin->getMsg('lobby');
                                if($p->hasPermission('sw.command.leave')) $msg .= $this->plugin->getMsg('onleave');
                                if($p->hasPermission('sw.command.join')) $msg .= $this->plugin->getMsg('onjoin');
                                if($p->hasPermission('sw.command.setlobby')) $msg .= $this->plugin->getMsg('setlobby');
                                if($p->hasPermission('sw.command.stop')) $msg .= $this->plugin->getMsg('stop');
                                if($p->hasPermission('sw.command.kick')) $msg .= $this->plugin->getMsg('kick');
                                if($p->hasPermission('sw.command.set')) $msg .= $this->plugin->getMsg('set');
                                if($p->hasPermission('sw.command.delete')) $msg .= $this->plugin->getMsg('delete');
                                if($p->hasPermission('sw.command.create')) $msg .= $this->plugin->getMsg('create');
                                $p->sendMessage($msg);
                                break;
                case "create":
                        if(!$p->hasPermission("sw.command.create")){
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                        break;
                    }
			if(file_exists($this->plugin->getServer()->getDataPath() . "/worlds/" . $args[1])){
				$this->plugin->getServer()->loadLevel($args[1]);
				$this->plugin->getServer()->getLevelByName($args[1])->loadChunk($this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
				array_push($this->plugin->arenas,$args[1]);
				$this->plugin->currentLevel = $args[1];
				$this->plugin->mode = 1;
				$p->sendMessage($this->plugin->getPrefix() . "You are about to register an arena. Tap a block to set a spawn point there!");
				$p->setGamemode(1);
				$p->teleport($this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
			}
			else
			{
				$p->sendMessage($this->plugin->getPrefix() . "There is no world with this name.");
			}
                                break;
                case "setlobby":
                        if(!$p->hasPermission('sw.command.setlobby')){
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                        break;
                    }
                        if(isset($args[1])){
                            $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('setlobby_help'));
                        break;
                    }
                        $this->plugin->setters[strtolower($p->getName())]['type'] = "mainlobby";
                        $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('break_block'));
                        break;
                    default:
                        $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('help'));
                        }
                        return;
                        }
                        $p->sendMessage('run command only in-game');
                        return;
                    }
                    $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('help'));
            }
        }  
}
    

