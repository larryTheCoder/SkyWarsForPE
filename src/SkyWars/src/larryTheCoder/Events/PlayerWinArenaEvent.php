<?php

namespace larryTheCoder\Events;

use larryTheCoder\SkyWarsAPI;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;
use larryTheCoder\Arena\Arena;

class PlayerWinArenaEvent extends PluginEvent{
	protected $player;
    protected $arena;
    
    public static $handlerList = null;
    
    public function __construct(SkyWarsAPI $plugin, Player $player, Arena $arena){
        parent::__construct($plugin);
        $this->player = $player;
        $this->arena = $arena;
    }
    
    public function getPlayer(){
        return $this->player;
    }
    
    public function getArena(){
        return $this->arena;
    }
    
    public function getArenaName(){
        return $this->arena->id;
    }
}