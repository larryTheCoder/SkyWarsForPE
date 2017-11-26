<?php

namespace larryTheCoder\events;

use larryTheCoder\arena\Arena;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;

/**
 * This event will be called if a player wins an arena
 *
 * @package larryTheCoder\events
 */
class PlayerWinArenaEvent extends PluginEvent {

	public static $handlerList = null;
	/** @var Player[] */
	protected $players = [];
	protected $arena;

	public function __construct(SkyWarsPE $plugin, Player $player, Arena $arena) {
		parent::__construct($plugin);
		$this->players = $player;
		$this->arena = $arena;
	}

	public function getPlayers() {
		return $this->players;
	}

	public function getArena() {
		return $this->arena;
	}

}