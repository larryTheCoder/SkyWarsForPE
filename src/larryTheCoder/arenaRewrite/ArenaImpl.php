<?php

/*
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
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

declare(strict_types = 1);

namespace larryTheCoder\arenaRewrite;

use larryTheCoder\arenaRewrite\api\Arena;
use larryTheCoder\arenaRewrite\api\impl\ArenaListener;
use larryTheCoder\arenaRewrite\api\SignManager;
use larryTheCoder\arenaRewrite\api\task\ArenaTickTask;
use larryTheCoder\arenaRewrite\task\SkyWarsTask;
use larryTheCoder\SkyWarsPE;
use pocketmine\level\Level;
use pocketmine\Player;

class ArenaImpl extends Arena {
	use ArenaData;

	// Allow invincible period on this arena.
	const ARENA_INVINCIBLE_PERIOD = 0x3;

	/** @var EventListener */
	private $eventListener;
	/** @var array */
	private $arenaData;
	/** @var SignManager */
	private $signManager;

	public function __construct(SkyWarsPE $plugin, array $arenaData){
		$this->arenaData = $arenaData;
		$this->parseData();

		$this->eventListener = new EventListener($this);
		$this->signManager = new SignManager($this, $this->getSignPosition());

		parent::__construct($plugin);
	}

	public function getArenaData(): array{
		return $this->arenaData;
	}

	public function initArena(Level $level, bool $isLobby): void{
		if($isLobby){

		}else{

		}
	}

	public function getCodeName(): string{
		return "Seven Red Suns";
	}

	public function startArena(): void{

	}

	public function stopArena(): void{

	}

	public function playerSpectate(Player $player): void{

	}

	public function unsetPlayer(Player $player){

	}

	public function getMinPlayer(): int{
		return $this->minimumPlayers;
	}

	public function getMaxPlayer(): int{
		return $this->maximumPlayers;
	}

	public function getEventListener(): ArenaListener{
		return $this->eventListener;
	}

	public function getSignManager(): SignManager{
		return $this->signManager;
	}

	public function getArenaTask(): ArenaTickTask{
		return new SkyWarsTask($this);
	}

	public function shutdown(): void{

	}
}