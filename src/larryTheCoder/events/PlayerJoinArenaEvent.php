<?php

namespace larryTheCoder\events;

use larryTheCoder\arena\Arena;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;

/**
 * An Event that will be called when a player join to an arena
 *
 * @package larryTheCoder\events
 */
class PlayerJoinArenaEvent extends PluginEvent implements Cancellable {

	public static $handlerList = null;
	protected $player;
	protected $arena;

	public function __construct(SkyWarsPE $plugin, Player $player, Arena $arena) {
		parent::__construct($plugin);
		$this->player = $player;
		$this->arena = $arena;
	}

	public function getPlayer() {
		return $this->player;
	}

	public function getArena() {
		return $this->arena;
	}

}
