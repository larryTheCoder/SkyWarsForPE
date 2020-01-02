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

	/** @var Config */
	public $arena;
	/** @var string */
	private $arenaName;
	/** @var SkyWarsPE */
	private $plugin;

	public function __construct(string $arenaName, SkyWarsPE $plugin){
		$this->arenaName = $arenaName;
		$this->plugin = $plugin;
		$this->arena = new Config($this->plugin->getDataFolder() . "arenas/$arenaName.yml", Config::YAML);
	}

	public function setJoinSign(int $x, int $y, int $z, string $level){ # OK
		$this->arena->setNested('signs.join-sign-x', $x);
		$this->arena->setNested('signs.join-sign-y', $y);
		$this->arena->setNested('signs.join-sign-z', $z);
		$this->arena->setNested('signs.join-sign-world', $level);
		$this->arena->save();
	}

	public function setChestPriority(bool $type){
		$this->arena->setNested('chest.refill', $type);
		$this->arena->save();
	}

	public function setStatus(bool $type){# OK
		$this->arena->setNested('signs.enable-status', $type);
		$this->arena->save();
	}

	public function setStatusLine(int $line, string $type){# OK
		$this->arena->setNested("signs.status-line-$line", $type);
		$this->arena->save();
	}

	public function setArenaWorld(string $type){# OK
		$this->arena->setNested('arena.arena-world', $type);
		$this->arena->save();
	}

	public function enableSpectator(bool $data){# OK
		$this->arena->setNested('arena.spectator-mode', $data);
		$this->arena->save();
	}

	public function setSpecSpawn(int $x, int $y, int $z){# OK
		$this->arena->setNested('arena.spec-spawn-x', $x);
		$this->arena->setNested('arena.spec-spawn-y', $y);
		$this->arena->setNested('arena.spec-spawn-z', $z);
		$this->arena->save();
	}

	public function setPlayersCount(int $maxPlayer, int $minPlayer){
		$this->arena->setNested('arena.max-players', $maxPlayer);
		$this->arena->setNested('arena.min-players', $minPlayer);
		$this->arena->save();
	}

	public function setStartTime(int $data){# OK
		$this->arena->setNested('arena.starting-time', $data);
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

	public function setGraceTimer(int $graceTimer){
		$this->arena->setNested('arena.grace-time', $graceTimer);
		$this->arena->save();
	}

	public function startOnFull(bool $startWhenFull){
		$this->arena->setNested('arena.start-when-full', $startWhenFull);
		$this->arena->save();
	}

	public function applyFullChanges(){
		$this->plugin->getArenaManager()->setArenaData($this->arena, $this->arenaName);
	}

	public function setSpawnPosition(array $pos, int $pedestal){
		$this->arena->setNested("arena.spawn-positions.pos-$pedestal", implode(":", $pos));
		$this->arena->save();
	}

	public function setArenaName(string $arenaName){
		$this->arena->set("arena-name", $arenaName);
	}

}
