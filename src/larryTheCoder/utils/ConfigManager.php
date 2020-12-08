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

use pocketmine\utils\Config;

/**
 * Config manager class for arenas that
 * will be used to configure the arena
 *
 * @package larryTheCoder\utils
 */
class ConfigManager {

	/** @var Config */
	public $config;
	/** @var string */
	public $fileName;
	/** @var string */
	public $arenaName;

	public function __construct(string $fileName, Config $arenaConfig){
		$this->fileName = $fileName;
		$this->config = $arenaConfig;
		$this->arenaName = $arenaConfig->get("arena-name", null);
	}

	public function getConfig(): Config{
		return $this->config;
	}

	public function setJoinSign(int $x, int $y, int $z, string $level): ConfigManager{ # OK
		$this->config->setNested('signs.join-sign-x', $x);
		$this->config->setNested('signs.join-sign-y', $y);
		$this->config->setNested('signs.join-sign-z', $z);
		$this->config->setNested('signs.join-sign-world', $level);

		return $this;
	}

	public function setStatusLine(string $type, int $line): ConfigManager{# OK
		$this->config->setNested("signs.status-line-$line", $type);

		return $this;
	}

	public function setArenaWorld(string $type): ConfigManager{# OK
		$this->config->setNested('arena.arena-world', $type);

		return $this;
	}

	public function enableSpectator(bool $data): ConfigManager{# OK
		$this->config->setNested('arena.spectator-mode', $data);
		$this->config->save();

		return $this;
	}

	public function setSpecSpawn(int $x, int $y, int $z): ConfigManager{# OK
		$this->config->setNested('arena.spec-spawn-x', $x);
		$this->config->setNested('arena.spec-spawn-y', $y);
		$this->config->setNested('arena.spec-spawn-z', $z);

		return $this;
	}

	public function setPlayersCount(int $maxPlayer, int $minPlayer): ConfigManager{
		$this->config->setNested('arena.max-players', $maxPlayer);
		$this->config->setNested('arena.min-players', $minPlayer);

		return $this;
	}

	public function setStartTime(int $data): ConfigManager{# OK
		$this->config->setNested('arena.starting-time', $data);

		return $this;
	}

	public function setEnable(bool $data): ConfigManager{# OK
		$this->config->set('enabled', $data);

		return $this;
	}

	public function setGraceTimer(int $graceTimer): ConfigManager{
		$this->config->setNested('arena.grace-time', $graceTimer);

		return $this;
	}

	public function startOnFull(bool $startWhenFull): ConfigManager{
		$this->config->setNested('arena.start-when-full', $startWhenFull);

		return $this;
	}

	public function saveArena(): ConfigManager{
		$this->config->save();

		return $this;
	}

	/**
	 * @param float[] $pos
	 * @param int $pedestal
	 * @return ConfigManager
	 */
	public function setSpawnPosition(array $pos, int $pedestal): ConfigManager{
		$this->config->setNested("arena.spawn-positions.pos-$pedestal", implode(":", $pos));

		return $this;
	}

	public function setArenaName(string $arenaName): ConfigManager{
		$this->config->set("arena-name", $arenaName);

		return $this;
	}

	public function resetSpawnPedestal(): ConfigManager{
		$this->config->setNested("arena.spawn-positions", []);

		return $this;
	}

	public function setTeamMode(bool $isTeam): ConfigManager{
		$this->config->set("arena-mode", $isTeam ? 1 : 0);

		return $this;
	}

	/**
	 * @param int $maxPlayer
	 * @param int $minTeams
	 * @param int $maxTeams
	 * @param int[] $teamColours
	 * @return ConfigManager
	 */
	public function setTeamData(int $maxPlayer, int $minTeams, int $maxTeams, array $teamColours): ConfigManager{
		$this->config->setNested("team-settings.players-per-team", $maxPlayer);
		$this->config->setNested("team-settings.minimum-teams", $minTeams);
		$this->config->setNested("team-settings.maximum-teams", $maxTeams);
		$this->config->setNested("team-settings.team-colours", $teamColours);

		return $this;
	}
}
