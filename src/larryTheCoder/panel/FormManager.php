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

namespace larryTheCoder\panel;

use larryTheCoder\arena\api\task\AsyncDirectoryDelete;
use larryTheCoder\arena\api\task\CompressionAsyncTask;
use larryTheCoder\arena\api\translation\TranslationContainer as TC;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\forms\CustomForm;
use larryTheCoder\forms\CustomFormResponse;
use larryTheCoder\forms\elements\Button;
use larryTheCoder\forms\elements\Dropdown;
use larryTheCoder\forms\elements\Image;
use larryTheCoder\forms\elements\Input;
use larryTheCoder\forms\elements\Label;
use larryTheCoder\forms\elements\Slider;
use larryTheCoder\forms\elements\Toggle;
use larryTheCoder\forms\FormQueue;
use larryTheCoder\forms\MenuForm;
use larryTheCoder\forms\ModalForm;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\cage\CageManager;
use larryTheCoder\utils\PlayerData;
use larryTheCoder\utils\Settings;
use larryTheCoder\worker\LevelAsyncPool;
use pocketmine\block\Slab;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class FormManager implements Listener {

	private const SET_SPAWN_COORDINATES = 0;
	private const SET_SPECTATOR_COORDINATES = 1;
	private const SET_JOIN_SIGN_COORDINATES = 2;
	private const SET_NPC_COORDINATES = 3;
	private const TELEPORT_TO_WORLD = 4;

	/** @var SkyWarsPE */
	private $plugin;
	/** @var ArenaImpl[] */
	private $arenaSetup = [];
	/** @var int[] */
	private $blockEvent = [];
	/** @var int[] */
	private $spawnCache = [];
	/** @var array<string, array<int|Item>> */
	private $lastHoldIndex = [];

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;

		$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
	}

	public function showSpectatorPanel(Player $player, ArenaImpl $arena): void{
		$buttons = [];
		foreach($arena->getPlayerManager()->getAlivePlayers() as $inGame){
			$buttons[] = new Button($inGame->getName());
		}

		$buttons[] = new Button(TC::getTranslation($player, 'spectator-exit'), new Image("textures/blocks/barrier", Image::TYPE_PATH));

		$form = new MenuForm(TC::getTranslation($player, 'spectator-select1'), TC::getTranslation($player, 'spectator-select2'), $buttons, function(Player $player, Button $selected) use ($arena): void{
			// Do not attempt to do anything if the arena is no longer running.
			// Or the player is no longer in arena
			if(!$arena->getPlayerManager()->isInArena($player)){
				$player->sendMessage(TC::getTranslation($player, 'spectator-not-ingame'));

				return;
			}

			$target = $arena->getPlayerManager()->getOriginPlayer($selected->getText());
			if($target === null){
				$player->sendMessage(TC::getTranslation($player, 'spectator-player-left'));

				return;
			}
			$player->teleport($target);
		}, function(Player $player): void{
			$player->sendMessage(TC::getTranslation($player, "panel-cancelled"));
		});

		FormQueue::sendForm($player, $form);
	}

	public function showStatsPanel(Player $player, string $target): void{
		SkyWarsDatabase::getPlayerEntry($target, function(?PlayerData $result) use ($player){
			if($result === null){
				$player->sendMessage(Settings::$prefix . TC::getTranslation($player, 'no-data'));

				return;
			}

			$form = new CustomForm(TC::getTranslation($player, 'stats-1', ["{PLAYER}" => $result->player]), [
				new Label(TC::getTranslation($player, 'stats-2', ["{DATA}" => $result->player])),
				new Label(TC::getTranslation($player, 'stats-3', ["{DATA}" => $result->kill])),
				new Label(TC::getTranslation($player, 'stats-4', ["{DATA}" => $result->death])),
				new Label(TC::getTranslation($player, 'stats-5', ["{DATA}" => ($result->kill / ($result->death === 0 ? 1 : $result->death))])), // a denominator cannot ever be 0
				new Label(TC::getTranslation($player, 'stats-6', ["{DATA}" => $result->wins])),
				new Label(TC::getTranslation($player, 'stats-7', ["{DATA}" => $result->lost])),
			], function(Player $player, CustomFormResponse $response): void{
			});

			FormQueue::sendForm($player, $form);
		});
	}

	//////////////////////////////////////// ARENA SETUP RELATED FUNCTIONS ////////////////////////////////////////

	/**
	 * The function that handles player arena creation, notifies player after he/she
	 * has successfully created the arenas.
	 *
	 * @param Player $player
	 */
	public function setupArena(Player $player): void{
		$worldPath = Server::getInstance()->getDataPath() . 'worlds/';

		// Proper way to do this instead of foreach.
		$files = array_filter(scandir($worldPath), function($file) use ($worldPath): bool{
			if($file === "." || $file === ".." ||
				Server::getInstance()->getDefaultLevel()->getFolderName() === $file ||
				is_file($worldPath . $file)){

				return false;
			}

			return empty(array_filter($this->plugin->getArenaManager()->getArenas(), function($arena) use ($file): bool{
				return $arena->getLevelName() === $file;
			}));
		});

		if(empty($files)){
			$player->sendMessage(TC::getTranslation($player, "no-world"));

			return;
		}

		$form = new CustomForm(TC::getTranslation($player, 'setup-arena-1'), [
			new Input(TC::getTranslation($player, 'setup-arena-2'), "Donkey Island"),
			new Dropdown(TC::getTranslation($player, 'setup-arena-3'), $files),
			new Slider(TC::getTranslation($player, 'setup-arena-4'), 4, 40),
			new Slider(TC::getTranslation($player, 'setup-arena-5'), 2, 40),
			new Toggle(TC::getTranslation($player, 'setup-arena-6'), true),
			new Toggle(TC::getTranslation($player, 'setup-arena-7'), true),
		], function(Player $player, CustomFormResponse $response): void{
			$am = $this->plugin->getArenaManager();

			$arenaName = (string)$response->getInput()->getValue();
			$arenaLevel = (string)$response->getDropdown()->getSelectedOption();
			$maxPlayer = (int)$response->getSlider()->getValue();
			$minPlayer = (int)$response->getSlider()->getValue();
			$spectator = (bool)$response->getToggle()->getValue();
			$startWhenFull = (bool)$response->getToggle()->getValue();

			// Check if the arena name is the same, otherwise we notify the player
			// that this arena has already exists.
			if($am->getArena($arenaName) !== null){
				$player->sendMessage(TC::getTranslation($player, 'arena-exists'));

				return;
			}

			// Faster way to get around variables.
			$arena = $am->createArena($arenaName);
			$arena->setConfig($arena->getConfigManager()
				->setArenaWorld($arenaLevel)
				->setArenaName($arenaName)
				->enableSpectator($spectator)
				->setPlayersCount($maxPlayer > $minPlayer ? $maxPlayer : $minPlayer, $minPlayer)
				->startOnFull($startWhenFull)
				->saveArena(), true);

			// Unload the level, this is needed in order to copy the arena worlds
			// into directive arenas plugin's path world directory.
			$level = Server::getInstance()->getLevelByName($arenaLevel);
			if($level !== null) Server::getInstance()->unloadLevel($level, true);

			// Copy files to the directive location, then we put on the modal form in next tick.
			$task = new CompressionAsyncTask([
				Server::getInstance()->getDataPath() . "worlds/" . $arenaLevel,
				$this->plugin->getDataFolder() . 'arenas/worlds/' . $arenaLevel . ".zip",
				true,
			], function() use ($player, $arena){
				$form = new ModalForm(TC::getTranslation($player, 'setup-arena-spawn-1'), TC::getTranslation($player, 'setup-arena-spawn-2'),
					function(Player $player, bool $response) use ($arena): void{
						if($response){
							$this->setupSpawn($player, $arena);
						}else{
							$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, false);

							$player->sendMessage(TC::getTranslation($player, 'setup-later'));
						}
					}, TC::getTranslation($player, 'setup-arena-spawn-3'), TC::getTranslation($player, 'setup-arena-spawn-4'));

				FormQueue::sendForm($player, $form);
			});

			LevelAsyncPool::getAsyncPool()->submitTask($task);
		}, function(Player $pl): void{
			$pl->sendMessage(TC::getTranslation($pl, 'panel-cancelled'));
		});

		FormQueue::sendForm($player, $form);
	}

	public function showSettingPanel(Player $player): void{
		$form = new MenuForm(TC::getTranslation($player, 'setup-choose-arena'));

		/** @var ArenaImpl[] $arenas */
		$arenas = [];
		foreach($this->plugin->getArenaManager()->getArenas() as $arena){
			$form->append(ucwords($arena->getMapName()));

			$arenas[] = $arena;
		}

		$form->setOnSubmit(function(Player $player, Button $selected) use ($arenas): void{
			$arena = $this->arenaSetup[$player->getName()] = $arenas[(int)$selected->getValue()];

			$arena->resetWorld();
			$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, true);

			$form = new MenuForm(TC::getTranslation($player, 'settings-arena-1', ["{ARENA_NAME}" => $arena->getMapName()]), "", [
				TC::getTranslation($player, 'settings-arena-2'),      // Arena Spawn
				TC::getTranslation($player, 'settings-arena-3'),      // Spectator spawn
				TC::getTranslation($player, 'settings-arena-4'),      // (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
				TC::getTranslation($player, 'settings-arena-5'),      // (Text) (Interval) (enable-interval)
				TC::getTranslation($player, 'settings-arena-6'),      // Sign location teleportation.
				TC::getTranslation($player, 'settings-arena-7'),      // Setup scoreboard.
				TC::getTranslation($player, 'settings-arena-8'),      // Editing the world.
				TC::getTranslation($player, 'settings-arena-9'),
			], function(Player $player, Button $selected) use ($arena): void{
				switch($selected->getValue()){
					case 0:
						$this->setupSpawn($player, $arena);
						break;
					case 1:
						$this->setupSpectator($player, $arena);
						break;
					case 2:
						$this->arenaBehaviour($player, $arena);
						break;
					case 3:
						$this->joinSignBehaviour($player, $arena);
						break;
					case 4:
						$this->setupJoinSign($player, $arena);
						break;
					case 5:
						$this->setupScoreboard($player, $arena);
						break;
					case 6:
						$this->teleportWorld($player, $arena);
						break;
					case 7:
						$this->deleteArena($player, $arena);
						break;
					default:
						$this->cleanupEvent($player);
						break;
				}
			}, function(Player $player): void{
				$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

				$this->cleanupEvent($player);
			});

			FormQueue::sendForm($player, $form);
		});
		$form->setOnClose(function(Player $player): void{
			$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		FormQueue::sendForm($player, $form);
	}

	private function arenaBehaviour(Player $player, ArenaImpl $arena): void{
		// (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
		$form = new CustomForm(TC::getTranslation($player, 'behaviour-setup-1'), [
			new Toggle(TC::getTranslation($player, 'behaviour-setup-2'), $arena->arenaEnable),
			new Slider(TC::getTranslation($player, 'behaviour-setup-3'), 0, 30, 1, $arena->arenaGraceTime),
			new Toggle(TC::getTranslation($player, 'behaviour-setup-4'), $arena->enableSpectator),
			new Toggle(TC::getTranslation($player, 'behaviour-setup-5'), $arena->getPlayerManager()->teamMode),
			new Toggle(TC::getTranslation($player, 'behaviour-setup-6'), $arena->arenaStartOnFull),
		], function(Player $player, CustomFormResponse $response) use ($arena): void{
			$enable = $response->getToggle()->getValue();
			$graceTimer = (int)$response->getSlider()->getValue();
			$spectatorMode = $response->getToggle()->getValue();
			$teamMode = $response->getToggle()->getValue();
			$startWhenFull = $response->getToggle()->getValue();

			$config = $arena->getConfigManager()
				->setEnable($enable)
				->setGraceTimer($graceTimer)
				->enableSpectator($spectatorMode)
				->startOnFull($startWhenFull)
				->saveArena();

			if($teamMode){
				$form = new CustomForm(TC::getTranslation($player, 'team-setup-1'), [
					new Slider(TC::getTranslation($player, 'team-setup-2'), 1, 16, 1.0, (int)$config->getConfig()->get('players-per-team', 3)),
					new Slider(TC::getTranslation($player, 'team-setup-3'), 1, 16, 1.0, (int)$config->getConfig()->get('minimum-teams', 2)),
					new Slider(TC::getTranslation($player, 'team-setup-4'), 1, 16, 1.0, (int)$config->getConfig()->get('maximum-teams', 3)),
					new Input(TC::getTranslation($player, 'team-setup-5'), '0;1;2;3', implode($config->getConfig()->get("team-colours", []))),
				], function(Player $player, CustomFormResponse $response) use ($config, $arena): void{
					$maxPlayer = (int)$response->getSlider()->getValue();
					$minTeams = (int)$response->getSlider()->getValue();
					$maxTeams = (int)$response->getSlider()->getValue();
					$input = $response->getInput()->getValue();

					$data = [];
					foreach(explode(';', $input) as $values){
						if(!is_numeric($values)){
							$player->sendMessage(TC::getTranslation($player, 'team-error-1'));
							$this->cleanupEvent($player);

							return;
						}

						$data[] = (int)$values;
					}

					if(count($data) < $maxTeams){
						$player->sendMessage(TC::getTranslation($player, 'team-error-2'));
						$this->cleanupEvent($player);

						return;
					}

					$config->setTeamMode(true)
						->setTeamData($maxPlayer, $minTeams >= $maxTeams ? $maxTeams : $minTeams, $maxTeams, $data)
						->saveArena();

					$this->cleanupEvent($player);

					$player->sendMessage(TC::getTranslation($player, 'behaviour-setup-complete', ["{ARENA_NAME}" => $arena->getMapName()]));
				}, function(Player $player): void{
					$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

					$this->cleanupEvent($player);
				});
			}else{
				$form = new CustomForm(TC::getTranslation($player, 'solo-setup-1'), [
					new Slider(TC::getTranslation($player, 'solo-setup-2'), 1, 16, 1.0, $arena->getMinPlayer()),
					new Slider(TC::getTranslation($player, 'solo-setup-3'), 1, 16, 1.0, $arena->getMaxPlayer()),
				], function(Player $player, CustomFormResponse $response) use ($config, $arena): void{
					$minPlayer = (int)$response->getSlider()->getValue();
					$maxPlayer = (int)$response->getSlider()->getValue();

					$config->setTeamMode(false)
						->setPlayersCount($maxPlayer > $minPlayer ? $maxPlayer : $minPlayer, $minPlayer)
						->saveArena();

					$this->cleanupEvent($player);

					$player->sendMessage(TC::getTranslation($player, 'behaviour-setup-complete', ["{ARENA_NAME}" => $arena->getMapName()]));
				}, function(Player $player): void{
					$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

					$this->cleanupEvent($player);
				});
			}

			FormQueue::sendForm($player, $form);
		}, function(Player $player): void{
			$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		FormQueue::sendForm($player, $form);
	}

	private function joinSignBehaviour(Player $player, ArenaImpl $arena): void{
		$form = new CustomForm(TC::getTranslation($player, 'behaviour-sign-1'), [
			new Label(TC::getTranslation($player, 'behaviour-sign-2')),
			new Label(TC::getTranslation($player, 'behaviour-sign-3')),
			new Input(TC::getTranslation($player, 'behaviour-sign-4'), "", $arena->statusLine1),
			new Input(TC::getTranslation($player, 'behaviour-sign-5'), "", $arena->statusLine2),
			new Input(TC::getTranslation($player, 'behaviour-sign-6'), "", $arena->statusLine3),
			new Input(TC::getTranslation($player, 'behaviour-sign-7'), "", $arena->statusLine4),
		], function(Player $player, CustomFormResponse $response) use ($arena): void{
			$arena->setConfig($arena->getConfigManager()
				->setStatusLine($response->getInput()->getValue(), 1)
				->setStatusLine($response->getInput()->getValue(), 2)
				->setStatusLine($response->getInput()->getValue(), 3)
				->setStatusLine($response->getInput()->getValue(), 4), true);

			$player->sendMessage(TC::getTranslation($player, 'behaviour-sign-complete', ["{ARENA_NAME}" => $arena->getMapName()]));

			$this->cleanupEvent($player);
		}, function(Player $player): void{
			$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		FormQueue::sendForm($player, $form);
	}

	public function setupSpawn(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage(TC::getTranslation($player, "setup-arena-spawn"));

			$arena->getConfigManager()->resetSpawnPedestal();

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::SET_SPAWN_COORDINATES;
		});
	}

	public function setupSpectator(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage(TC::getTranslation($player, "setup-arena-spectator"));

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::SET_SPECTATOR_COORDINATES;
		});
	}

	public function teleportWorld(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage(TC::getTranslation($player, "edit-world"));

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::TELEPORT_TO_WORLD;
		});
	}

	public function setupJoinSign(Player $player, ArenaImpl $arena): void{
		$this->setMagicWand($player);

		$player->sendMessage(TC::getTranslation($player, 'setup-arena-joinsign'));

		$this->arenaSetup[$player->getName()] = $arena;
		$this->blockEvent[$player->getName()] = self::SET_JOIN_SIGN_COORDINATES;
	}

	public function setupNPCCoordinates(Player $player): void{
		$this->setMagicWand($player);

		$player->sendMessage(TC::getTranslation($player, 'setup-npc'));

		$this->blockEvent[$player->getName()] = self::SET_NPC_COORDINATES;
	}

	private function deleteArena(Player $p, ArenaImpl $data): void{
		$form = new ModalForm("", TC::getTranslation($p, 'arena-delete-confirm'),
			function(Player $player, bool $response) use ($data): void{
				$this->cleanupEvent($player);

				if(!$response) return;

				$this->plugin->getArenaManager()->deleteArena($data);

				$player->sendMessage(TC::getTranslation($player, 'arena-delete', ["{ARENA}" => $data->getMapName()]));
			}, TC::getTranslation($p, 'arena-delete-1'), TC::getTranslation($p, 'arena-delete-2'));

		FormQueue::sendForm($p, $form);
	}

	private function setupScoreboard(Player $player, ArenaImpl $arena, int $id = -1): void{
		if($id === -1){
			$buttons = [
				new Button(TC::getTranslation($player, 'scoreboard-button-1')),
				new Button(TC::getTranslation($player, 'scoreboard-button-2')),
				new Button(TC::getTranslation($player, 'scoreboard-button-3')),
				new Button(TC::getTranslation($player, 'scoreboard-button-4')),
				new Button(TC::getTranslation($player, 'scoreboard-button-5'), new Image("textures/blocks/barrier", Image::TYPE_PATH)),
			];

			$form = new MenuForm(TC::getTranslation($player, 'setup-choose-arena'), "", $buttons, function(Player $player, Button $selected) use ($buttons, $arena): void{
				$selectedButton = $selected->getValue();
				if(!isset($buttons[$selectedButton])){
					$this->cleanupEvent($player);

					return;
				}

				if($selectedButton === 4){
					$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));
					$this->cleanupEvent($player);
				}else{
					$this->setupScoreboard($player, $arena, $selectedButton);
				}
			}, function(Player $player): void{
				$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

				$this->cleanupEvent($player);
			});

			FormQueue::sendForm($player, $form);

			return;
		}

		$configPath = $this->plugin->getDataFolder() . "scoreboards/" . $arena->getMapName() . ".yml";
		if(!is_file($configPath)){
			file_put_contents($configPath, $resource = $this->plugin->getResource("scoreboard.yml"));
			fclose($resource);
		}

		$config = new Config($configPath, Config::YAML);

		switch($id){
			case 0: // wait-arena
				$inputs = $this->recurseConfig($config->get("wait-arena", []));
				break;
			case 1: // in-game-arena
				$inputs = $this->recurseConfig($config->get("in-game-arena", []));
				break;
			case 2: // ending-state-arena
				$inputs = $this->recurseConfig($config->get("ending-state-arena", []));
				break;
			case 3: // spectate-scoreboard
				$inputs = $this->recurseConfig($config->get("spectate-scoreboard", []));
				break;
			default:
				$player->sendMessage(TC::getTranslation($player, 'form-error-1'));
				$this->cleanupEvent($player);

				return;
		}

		$elements = array_merge([new Label([
			TC::getTranslation($player, 'scoreboard-state-1'),
			TC::getTranslation($player, 'scoreboard-state-2'),
			TC::getTranslation($player, 'scoreboard-state-3'),
			TC::getTranslation($player, 'scoreboard-state-4'),
		][$id])], $inputs);

		$form = new CustomForm("Scoreboard setup", $elements, function(Player $player, CustomFormResponse $response) use ($arena, $config, $id): void{
			$this->cleanupEvent($player);

			$responses = [];

			$emptyElements = [];
			foreach($response->getElements() as $elements){
				if($elements instanceof Input){
					$element = (string)$elements->getValue();
					if(empty($element)){
						$emptyElements[] = $element;
						continue;
					}elseif(!empty($emptyElements)){
						$responses = array_merge($responses, $emptyElements);

						$emptyElements = [];
					}

					$responses[] = $element;
				}
			}

			unset($emptyElements); // Clear off unused variable from memory?

			$config->set([
				"wait-arena",
				"in-game-arena",
				"ending-state-arena",
				"spectate-scoreboard",
			][$id], $responses);
			$config->save();

			$arena->getScoreboard()->resetScoreboard();

			$player->sendMessage(Settings::$prefix . TC::getTranslation($player, 'scoreboard-success'));
		}, function(Player $player): void{
			$player->sendMessage(TC::getTranslation($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		FormQueue::sendForm($player, $form);
	}

	/**
	 * Recursively returns the string objects that are set in the config given
	 * for a specific key.
	 *
	 * @param string[] $metadata
	 * @return Input[]
	 */
	private function recurseConfig(array $metadata): array{
		$inputs = [];
		for($i = 1; $i <= 15; $i++){
			$inputs[] = new Input("Line #" . $i, "Placeholder #" . $i, $metadata[$i - 1] ?? "");
		}

		return $inputs;
	}
	//////////////////////////////////////// ARENA SETUP RELATED FUNCTIONS ////////////////////////////////////////

	/**
	 * Perform world-copy of an arena world to designated worlds folder and loads them.
	 *
	 * @param ArenaImpl $arena
	 * @param callable $onComplete
	 */
	private function performWorldCopy(ArenaImpl $arena, callable $onComplete): void{
		$level = Server::getInstance()->getLevelByName($arena->getLevelName());
		if($level !== null) Server::getInstance()->unloadLevel($level, true);

		$task = new CompressionAsyncTask([
			$this->plugin->getDataFolder() . 'arenas/worlds/' . $arena->getLevelName() . ".zip",
			Server::getInstance()->getDataPath() . "worlds/" . $arena->getLevelName(),
			false,
		], function() use ($arena, $onComplete){
			Server::getInstance()->loadLevel($arena->getLevelName());

			$level = Server::getInstance()->getLevelByName($arena->getLevelName());
			$level->setAutoSave(false);

			$level->setTime(Level::TIME_DAY);
			$level->stopTime();

			$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, true);

			$onComplete($level);
		});

		LevelAsyncPool::getAsyncPool()->submitTask($task);
	}

	/**
	 * Send this player a cage selection form. They can choose what
	 * cages they want to use for next game.
	 *
	 * @param Player $player
	 */
	public function showChooseCage(Player $player): void{
		$form = new MenuForm(TC::getTranslation($player, 'cage-selection-1'), TC::getTranslation($player, 'cage-selection-2'));

		$selectedCage = CageManager::getInstance()->getPlayerCage($player);

		$cages = [];
		foreach(CageManager::getInstance()->getCages() as $cage){
			if($selectedCage !== null && $selectedCage->getId() === $cage->getId()){
				$form->append(TC::getTranslation($player, 'cage-selected', ["{CAGE_NAME}" => $cage->getCageName()]));
			}elseif($cage->getPrice() > 0){
				if(!$player->hasPermission($cage->getCagePermission())){
					$form->append(TC::getTranslation($player, 'cage-buy', ["{CAGE_NAME}" => $cage->getCageName(), "{CAGE_PRICE}" => $cage->getPrice()]));
				}else{
					$form->append(TC::getTranslation($player, 'cage-bought', ["{CAGE_NAME}" => $cage->getCageName()]));
				}
			}else{
				$form->append(TC::getTranslation($player, 'cage-select', ["{CAGE_NAME}" => $cage->getCageName()]));
			}

			$cages[] = $cage;
		}

		$form->setOnSubmit(function(Player $player, Button $selected) use ($cages): void{
			CageManager::getInstance()->setPlayerCage($player, $cages[$selected->getValue()]);
		});

		FormQueue::sendForm($player, $form);
	}

	private function setMagicWand(Player $p): void{
		$this->lastHoldIndex[$p->getName()] = [$p->getInventory()->getHeldItemIndex(), $p->getInventory()->getHotbarSlotItem(0)];

		$p->setGamemode(1);
		$p->getInventory()->setHeldItemIndex(0);
		$p->getInventory()->setItemInHand($this->getMagicWand());
	}

	/**
	 * @param BlockBreakEvent $event
	 */
	public function onBlockBreakEvent(BlockBreakEvent $event): void{
		$player = $event->getPlayer();

		if(isset($this->blockEvent[$player->getName()]) && $event->getItem()->equals($this->getMagicWand())){
			$arena = $this->arenaSetup[$player->getName()] ?? null;
			$config = $arena === null ? null : $arena->getConfigManager();
			$block = $event->getBlock();

			switch($this->blockEvent[$player->getName()]){
				case self::SET_SPAWN_COORDINATES:
					if($arena->getLevelName() !== $player->getLevel()->getFolderName()){
						$player->sendMessage(TC::getTranslation($player, 'setup-wrong-world-1', ["{ARENA_WORLD}", $arena->getLevelName()]));
						break;
					}

					$mode = $this->spawnCache[$player->getName()] ?? 1;

					if($mode <= $arena->getMaxPlayer()){
						$config->setSpawnPosition([$block->getX(), $block->getY() + 1, $block->getZ()], $mode);

						$player->sendMessage(str_replace("{COUNT}", (string)$mode, TC::getTranslation($player, 'panel-spawn-pos')));

						if($mode === $arena->getMaxPlayer()){
							$player->sendMessage(TC::getTranslation($player, "panel-spawn-set"));
							$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

							$this->cleanupEvent($player, true);
							break;
						}

						$this->spawnCache[$player->getName()] = ++$mode;
					}
					break;
				case self::SET_SPECTATOR_COORDINATES:
					if($arena->getLevelName() !== $player->getLevel()->getFolderName()){
						$player->sendMessage(TC::getTranslation($player, 'setup-wrong-world-1', ["{ARENA_WORLD}", $arena->getLevelName()]));
						break;
					}

					$config->setSpecSpawn($block->getX(), $block->getY(), $block->getZ());

					$player->sendMessage(TC::getTranslation($player, 'panel-join-spect'));
					$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

					$this->cleanupEvent($player, true);
					break;
				case self::SET_JOIN_SIGN_COORDINATES:
					if($this->plugin->getArenaManager()->getPlayerArena($player) !== null){
						$player->sendMessage(TC::getTranslation($player, 'setup-wrong-world-2'));
						break;
					}

					$config->setJoinSign($block->getX(), $block->getY(), $block->getZ(), $block->level->getFolderName());

					$player->sendMessage(TC::getTranslation($player, 'panel-join-sign'));

					$this->cleanupEvent($player);
					break;
				case self::SET_NPC_COORDINATES:
					if($this->plugin->getArenaManager()->getPlayerArena($player) !== null){
						$player->sendMessage(TC::getTranslation($player, 'setup-wrong-world-2'));
						break;
					}

					$config = new Config($this->plugin->getDataFolder() . "npc.yml", Config::YAML);
					$mode = $this->spawnCache[$player->getName()] ?? 1;

					if($mode <= 3){
						$y = $block instanceof Slab ? 0.5 : 1;

						$config->set("npc-$mode", [$block->getX() + .5, $block->getY() + $y, $block->getZ() + .5, $block->level->getFolderName()]);
						$config->save();

						$player->sendMessage(str_replace("{COUNT}", (string)$mode, TC::getTranslation($player, 'panel-spawn-pos')));

						$this->spawnCache[$player->getName()] = ++$mode;
					}else{
						$player->sendMessage(TC::getTranslation($player, "panel-spawn-set"));
						$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

						$this->cleanupEvent($player);
					}
					break;
				case self::TELEPORT_TO_WORLD:
					$player->sendMessage(Settings::$prefix . TC::getTranslation($player, 'world-teleport'));

					$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

					$level = Server::getInstance()->getLevelByName($arena->getLevelName());
					$level->save(true);

					Server::getInstance()->unloadLevel($level, true);

					$task = new CompressionAsyncTask([
						Server::getInstance()->getDataPath() . "worlds/" . $arena->getLevelName(),
						$this->plugin->getDataFolder() . 'arenas/worlds/' . $arena->getLevelName() . ".zip",
						true,
					], function() use ($player){
						$this->cleanupEvent($player, true);
					});

					LevelAsyncPool::getAsyncPool()->submitTask($task);
			}

			$event->setCancelled();
		}
	}

	private function cleanupEvent(Player $player, bool $cleanWorld = false): void{
		$arena = $this->arenaSetup[$player->getName()] ?? null;
		if($arena !== null){
			$arena->setConfig($arena->getConfigManager()->saveArena(), true);
			$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, false);

			if($cleanWorld && ($level = Server::getInstance()->getLevelByName($arena->getLevelName())) !== null){
				LevelAsyncPool::getAsyncPool()->submitTask(new AsyncDirectoryDelete([$level]));
			}
		}

		if(isset($this->lastHoldIndex[$player->getName()])){
			$holdIndex = $this->lastHoldIndex[$player->getName()][0];
			$lastItem = $this->lastHoldIndex[$player->getName()][1];

			$player->getInventory()->setItem(0, $lastItem ?? ItemFactory::get(ItemIds::AIR));
			$player->getInventory()->setHeldItemIndex($holdIndex);

			unset($this->lastHoldIndex[$player->getName()]);
		}

		unset($this->arenaSetup[$player->getName()]);
		unset($this->spawnCache[$player->getName()]);
		unset($this->blockEvent[$player->getName()]);
	}

	private function getMagicWand(): Item{
		return ItemFactory::get(ItemIds::BLAZE_ROD);
	}
}