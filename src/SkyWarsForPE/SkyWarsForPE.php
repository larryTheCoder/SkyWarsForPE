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

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\plugin\Plugin;

class SkyWarsForPE extends pluginbase implements listener {
    
    public $cfg;
    
    public $economy;
    public $skywarsstarted = false;

    public function onLoad() {
        $this->initConfig();
        $this->getLogger()->info(TextFormat::YELLOW.Messages::getMsg("plugin-load"));        
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
        $this->getConfig()->save();
        $this->points()->save();
       
        $this->getServer()->info(TextFormat::RED.Message::getMsg("plugin_disabled"));
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
            $this->getServer()->getLogger()->info("Selected language {$this->cfg->get('Language')}");
        }
    }
       
    public function getMsg($key){
        $msg = $this->msg;
        return str_replace("&", "§", $msg->get($key));
    }
     
        
    public function onQuit(PlayerQuitEvent $e){
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }
    
    public function onKick(PlayerKickEvent $e){
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }
    public function onBlockBreak(BlockBreakEvent $event){
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('lobby') and !$event->getPlayer()->hasPermission("skywars.editlobby") || !$event->getPlayer()->hasPermission("skywars")){ //if level is lobby and player hasn't the permission to modify it
		$event->setCancelled(); // cancel the event
		$event->getPlayer()->sendMessage("You don't have permission to edit the lobby.");
	}
    }
	
    public function onBlockPlace(BlockPlaceEvent $event){
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('lobby') and !$event->getPlayer()->hasPermission("skywars.editlobby") || !$event->getPlayer()->hasPermission("skywars")){
		$event->setCancelled();
		$event->getPlayer()->sendMessage("You don't have permission to edit the lobby.");
	}
	if($event->getPlayer()->getLevel()->getName() == $this->getConfig()->get('aworld') and $event->getPlayer()->getGameMode() == 3){
		$event->setCancelled();
	}
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

}
