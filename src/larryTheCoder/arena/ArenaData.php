<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2019 larryTheCoder and contributors
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

namespace larryTheCoder\arena;


use larryTheCoder\utils\Utils;
use pocketmine\math\Vector3;
use pocketmine\Server;

/**
 * Stores everything about the arena config
 * file into a set of variables.
 *
 * @package larryTheCoder\arenaRewrite
 */
trait ArenaData {

	public $configVersion = 1;
	public $gameAPICodename = "Default API";
	public $inSetup = false;
	public $configChecked = false;

	// The root of the config.
	public $arenaEnable = false;
	public $arenaName = "";
	public $arenaMode = State::MODE_SOLO;

	// Team settings
	public $playerPerTeam = 0;
	public $playerMinimum = 0;
	public $worldTeamMembers = 0;
	public $monarchySystem = false;
	public $interactiveSpawns = false;

	// Winners section
	public $winnersCommand = [];

	// Signs section.
	public $enableJoinSign = false;
	public $joinSignVec = null;
	public $statusLine1 = "";
	public $statusLine2 = "";
	public $statusLine3 = "";
	public $statusLine4 = "";
	public $joinSignWorld = "";
	public $statusLineUpdate = 2;

	// Chest section.
	public $refillChest = true;
	public $refillAverage = [240];

	// Arena section.
	public $arenaTime = 0;
	public $arenaWorld = "";
	public $arenaSpecPos = null;
	public $spawnPedestals = [];
	public $maximumPlayers = 0;
	public $minimumPlayers = 0;
	public $arenaGraceTime = 0;
	public $spectateWaiting = 0;
	public $enableSpectator = false;
	public $arenaStartOnFull = false;
	public $arenaBroadcastTM = [];
	public $arenaMoneyReward = 0;
	public $arenaStartingTime = 0;

	/**
	 * Parses the data for the arena
	 */
	public function parseData(){
		$data = $this->getArenaData();

		try{
			if($data['version'] !== $this->configVersion){
				throw new \InvalidArgumentException("Unsupported config version for {$this->gameAPICodename}");
			}

			// Root of the config.
			$this->arenaEnable = boolval($data["enabled"]);
			$this->arenaName = $data['arena-name'];
			$this->arenaMode = $data['arena-mode'];

			// Signs config.
			$signs = $data['signs'];
			$this->enableJoinSign = boolval($signs['enable-status']);
			$this->joinSignVec = new Vector3($signs['join-sign-x'], $signs['join-sign-y'], $signs['join-sign-z']);
			$this->statusLine1 = $signs['status-line-1'];
			$this->statusLine2 = $signs['status-line-2'];
			$this->statusLine3 = $signs['status-line-3'];
			$this->statusLine4 = $signs['status-line-4'];
			$this->joinSignWorld = $signs['join-sign-world'];
			$this->statusLineUpdate = $signs['sign-update-time'];

			// Chest config
			$chest = $data['chest'];
			$this->refillChest = boolval($chest['refill']);
			$this->refillAverage = $chest['refill-average'];

			// Winner config
			$winner = $data['winners'];
			if(!is_array($winner)){
				$this->winnersCommand[] = $winner["command-execute"];
			}else{
				foreach($winner as $id => $command){
					$this->winnersCommand[] = $command;
				}
			}

			// Arena config
			$arena = $data['arena'];
			$this->arenaWorld = $arena['arena-world'];
			$this->arenaSpecPos = new Vector3($arena['spec-spawn-x'], $arena['spec-spawn-y'], $arena['spec-spawn-z']);
			$this->arenaGraceTime = intval($arena['grace-time']);
			$this->enableSpectator = boolval($arena['spectator-mode']);
			if(is_int($arena['time'])){
				$this->arenaTime = intval($arena['time']);
			}else{
				$this->arenaTime = str_replace(['true', 'day', 'night'], [-1, 6000, 18000], $arena['time']);
			}
			$this->arenaMoneyReward = intval($arena['money-reward']);
			$this->arenaBroadcastTM = explode(':', $arena['finish-msg-levels']);
			$this->arenaStartOnFull = boolval($arena['start-when-full']);
			$this->maximumPlayers = intval($arena['max-players']);
			$this->minimumPlayers = intval($arena['min-players']);
			$this->arenaStartingTime = intval($arena['starting-time']);
			foreach($arena['spawn-positions'] as $val => $pos){
				$strPos = explode(':', $pos);

				$this->spawnPedestals[] = new Vector3(intval($strPos[0]), intval($strPos[1]), intval($strPos[2]));
			}

			// Team data(s)
			if($data['arena-mode'] === State::MODE_TEAM){
				Utils::sendDebug("Overriding {$this->arenaName} default players settings");

				$this->maximumTeams = $data['team-settings']['world-teams-avail'];     // Maximum teams   in arena
				$this->maximumMembers = $data['team-settings']['players-per-team'];    // Maximum members in team
				$this->maximumPlayers = $this->maximumMembers * $this->maximumTeams;   // Maximum players in arena
				$this->minimumPlayers = $this->minimumMembers * $this->maximumTeams;   // Minimum players in arena
			}

			// Verify spawn pedestals.
			$spawnPedestals = count($this->spawnPedestals);
			if(($this->teamMode && ($this->playerPerTeam * $this->worldTeamMembers) > $spawnPedestals) || $this->maximumPlayers > $spawnPedestals){
				Utils::send("§6" . ucwords($this->arenaName) . " §a§l-§r§c Spawn pedestals is not configured correctly.");
				throw new \Exception("Spawn pedestals is not configured correctly.");
			}elseif(($this->teamMode && ($this->playerPerTeam * $this->worldTeamMembers) < $spawnPedestals) || $this->maximumPlayers < $spawnPedestals){
				Utils::send("§6" . ucwords($this->arenaName) . " §a§l-§r§e Spawn pedestals is over configured.");
			}
		}catch(\Exception $ex){
			Utils::send("§6" . ucwords($this->arenaName) . " §a§l-§r§c Failed to verify config files.");
			$this->arenaEnable = false;

			Server::getInstance()->getLogger()->logException($ex);
		}
		$this->configChecked = true;

		if($this->arenaEnable){
			Utils::send("§6" . ucwords($this->arenaName) . " §a§l-§r§a Arena loaded and enabled");
		}else{
			Utils::send("§6" . ucwords($this->arenaName) . " §a§l-§r§c Arena disabled");
		}
	}

	/**
	 * Returns the data of the arena.
	 *
	 * @return array
	 */
	public abstract function getArenaData();
}