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

namespace larryTheCoder\utils;

use larryTheCoder\SkyWarsPE;
use pocketmine\utils\Config;

/**
 * Config manager class for arenas that
 * will be used to configure the arena
 *
 * @package larryTheCoder\utils
 */
class ConfigManager {

	public $arena;
	private $id;
	private $plugin;

	public function __construct(string $id, SkyWarsPE $plugin){
		$this->id = $id;
		$this->plugin = $plugin;
		$this->arena = new Config($this->plugin->getDataFolder() . "arenas/$id.yml", Config::YAML);
	}

	public function setJoinSign(int $x, int $y, int $z, string $level){ # OK
		$this->arena->setNested('signs.join_sign_x', $x);
		$this->arena->setNested('signs.join_sign_y', $y);
		$this->arena->setNested('signs.join_sign_z', $z);
		$this->arena->setNested('signs.join_sign_world', $level);
		$this->arena->save();
	}

	public function setChestPriority(bool $type){
		$this->arena->setNested('chest.refill', $type);
		$this->arena->save();
	}

	public function setMoney(int $type){
		$this->arena->setNested('arena.money_reward', $type);
		$this->arena->save();
	}

	public function setStatus(bool $type){# OK
		$this->arena->setNested('signs.enable_status', $type);
		$this->arena->save();
	}

	public function setStatusLine(int $line, string $type){# OK
		$this->arena->setNested("signs.status_line_$line", $type);
		$this->arena->save();
	}

	public function setUpdateTime(int $type){# OK
		$this->arena->setNested('signs.sign_update_time', $type);
		$this->arena->save();
	}

	public function setArenaWorld(string $type){# OK
		$this->arena->setNested('arena.arena_world', $type);
		$this->arena->save();
	}

	public function setSpectator(bool $data){# OK
		$this->arena->setNested('arena.spectator_mode', $data);
		$this->arena->save();
	}

	public function setSpecSpawn(int $x, int $y, int $z){# OK
		$this->arena->setNested('arena.spec_spawn_x', $x);
		$this->arena->setNested('arena.spec_spawn_y', $y);
		$this->arena->setNested('arena.spec_spawn_z', $z);
		$this->arena->save();
	}

	public function setMaxPlayers(int $data){# OK
		$this->arena->setNested('arena.max_players', $data);
		$this->arena->save();
	}

	public function setMinPlayers(int $data){# OK
		$this->arena->setNested('arena.min_players', $data);
		$this->arena->save();
	}

	public function setStartTime(int $data){# OK
		$this->arena->setNested('arena.starting_time', $data);
		$this->arena->save();
	}

	public function setTime(string $data){# OK
		$this->arena->setNested('arena.time', $data);
		$this->arena->save();
	}

	public function setEnable(bool $data){# OK
		$this->arena->set('enabled', $data);
		$this->arena->save();
	}

	public function setChestTicks(int $data){
		$this->arena->setNested('chest.refill_rate', $data);
		$this->arena->save();
	}

}
