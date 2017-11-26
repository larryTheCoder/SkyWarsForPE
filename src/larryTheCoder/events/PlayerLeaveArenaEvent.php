<?php
/**
 * Created by PhpStorm.
 * User: Amir Muazzam
 * Date: 11/26/2017
 * Time: 11:36 AM
 */

namespace larryTheCoder\events;


use larryTheCoder\arena\Arena;
use larryTheCoder\SkyWarsPE;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;

/**
 * This event will be called when a player attempted to leave arena
 * when player is in arena that not ended yet by calling '/leave'
 *
 * @package larryTheCoder\events
 */
class PlayerLeaveArenaEvent extends PluginEvent implements Cancellable {
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