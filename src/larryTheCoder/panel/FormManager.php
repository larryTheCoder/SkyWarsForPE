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

use larryTheCoder\arena\api\impl\ArenaState;
use larryTheCoder\arena\api\task\AsyncDirectoryDelete;
use larryTheCoder\arena\api\task\CompressionAsyncTask;
use larryTheCoder\arena\ArenaImpl;
use larryTheCoder\forms\CustomForm;
use larryTheCoder\forms\CustomFormResponse;
use larryTheCoder\forms\elements\Button;
use larryTheCoder\forms\elements\Dropdown;
use larryTheCoder\forms\elements\Image;
use larryTheCoder\forms\elements\Input;
use larryTheCoder\forms\elements\Label;
use larryTheCoder\forms\elements\Slider;
use larryTheCoder\forms\elements\Toggle;
use larryTheCoder\forms\MenuForm;
use larryTheCoder\forms\ModalForm;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\utils\PlayerData;
use larryTheCoder\utils\Settings;
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
use pocketmine\utils\TextFormat;

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

		$buttons[] = new Button("Exit");

		$form = new MenuForm(TextFormat::BLUE . "Select Player Name", "Select a player to spectate", $buttons, function(Player $player, Button $selected) use ($arena): void{
			// Do not attempt to do anything if the arena is no longer running.
			// Or the player is no longer in arena
			if($arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING || !$arena->getPlayerManager()->isInArena($player)){
				$player->sendMessage(TextFormat::RED . "You are no longer in the arena.");

				return;
			}

			$target = $arena->getPlayerManager()->getOriginPlayer($selected->getValue());
			if($target === null){
				$player->sendMessage(TextFormat::RED . "That player is no longer in the arena.");

				return;
			}
			$player->teleport($target);
		}, function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, "panel-cancelled"));
		});

		$player->sendForm($form);
	}

	public function showStatsPanel(Player $player): void{
		// Checked and worked.
		$this->plugin->getDatabase()->getPlayerData($player->getName(), function(PlayerData $result) use ($player){
			$form = new CustomForm("§a{$result->player}'s stats", [
				new Label("§6Name: §f" . $result->player),
				new Label("§6Kills: §f" . $result->kill),
				new Label("§6Deaths: §f" . $result->death),
				new Label("§6Wins: §f" . $result->wins),
				new Label("§6Lost: §f" . $result->lost),
			], function(Player $player, CustomFormResponse $response): void{
			});

			$player->sendForm($form);
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
			$player->sendMessage($this->plugin->getMsg($player, "no-world"));

			return;
		}

		$form = new CustomForm("§5SkyWars Setup.", [
			new Input("§6The name of your Arena.", "Donkey Island"),
			new Dropdown("§6Select your Arena level.", $files),
			new Slider("§eMaximum players", 4, 40),
			new Slider("§eMinimum players", 2, 40),
			new Toggle("§7Spectator mode", true),
			new Toggle("§7Start on full", true),
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
				$player->sendMessage($this->plugin->getMsg($player, 'arena-exists'));

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
				$form = new ModalForm("Setup spawn?", "§aYou may need to setup arena's spawn position so system could enable the arena much faster.",
					function(Player $player, bool $response) use ($arena): void{
						if($response){
							$this->setupSpawn($player, $arena);
						}else{
							$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, false);

							$player->sendMessage("You can setup this later with /sw settings");
						}
					}, "Setup arena spawn.", "§cSetup later.");

				$player->sendForm($form);
			});

			Server::getInstance()->getAsyncPool()->submitTask($task);
		}, function(Player $pl): void{
			$pl->sendMessage($this->plugin->getMsg($pl, 'panel-cancelled'));
		});

		$player->sendForm($form);
	}

	public function showSettingPanel(Player $player): void{
		$form = new MenuForm("§aChoose your arena first.");

		/** @var ArenaImpl[] $arenas */
		$arenas = [];
		foreach($this->plugin->getArenaManager()->getArenas() as $arena){
			$form->append(ucwords($arena->getMapName()));

			$arenas[] = $arena;
		}

		$form->setOnSubmit(function(Player $player, Button $selected) use ($arenas): void{
			$arena = $this->arenaSetup[$player->getName()] = $arenas[(int)$selected->getValue()];

			$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, true);

			$form = new MenuForm("Setup for arena {$arena->getMapName()}", "", [
				"Setup Arena Spawn",            // Arena Spawn
				"Setup Spectator Spawn",        // Spectator spawn
				"Setup Arena Behaviour",        // (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
				"Set Join Sign Behaviour",      // (Text) (Interval) (enable-interval)
				"Set Join Sign Location",       // Sign location teleportation.
				"Setup Scoreboard",             // Setup scoreboard.
				"Edit this world",              // Editing the world.
				TextFormat::RED . "Delete this arena",
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
				$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

				$this->cleanupEvent($player);
			});

			$player->sendForm($form);
		});
		$form->setOnClose(function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		$player->sendForm($form);
	}

	private function arenaBehaviour(Player $player, ArenaImpl $arena): void{
		// (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
		$form = new CustomForm("Arena settings.", [
			new Toggle("§eEnable the arena?", $arena->arenaEnable),
			new Slider("§eSet Grace Timer", 0, 30, 1, $arena->arenaGraceTime),
			new Toggle("§eEnable Spectator Mode?", $arena->enableSpectator),
			new Slider("§eMaximum players to be in arena", 0, 50, 1, $arena->getMaxPlayer()),
			new Slider("§eMinimum players to be in arena", 0, 50, 1, $arena->getMinPlayer()),
			new Toggle("§eStart when full", $arena->arenaStartOnFull),
		], function(Player $player, CustomFormResponse $response) use ($arena): void{
			$enable = $response->getToggle()->getValue();
			$graceTimer = $response->getSlider()->getValue();
			$spectatorMode = $response->getToggle()->getValue();
			$maxPlayer = $response->getSlider()->getValue();
			$minPlayer = $response->getSlider()->getValue();
			$startWhenFull = $response->getToggle()->getValue();

			$arena->setConfig($arena->getConfigManager()
				->setEnable($enable)
				->setGraceTimer($graceTimer)
				->enableSpectator($spectatorMode)
				->setPlayersCount($maxPlayer > $minPlayer ? $maxPlayer : $minPlayer, $arena->getMinPlayer())
				->startOnFull($startWhenFull)
				->saveArena(), true);

			$player->sendMessage(TextFormat::GREEN . "Successfully updated arena " . TextFormat::YELLOW . $arena->getMapName());

			$this->cleanupEvent($player);
		}, function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		$player->sendForm($form);
	}

	private function joinSignBehaviour(Player $p, ArenaImpl $arena): void{
		$form = new CustomForm("§eForm Behaviour Setup", [
			new Label("§aWelcome to sign Behaviour Setup. First before you doing anything, you may need to know these"),
			new Label("§eStatus lines\n&a &b &c = you can use color with &\n%alive = amount of in-game players\n%dead = amount of dead players\n%status = game status\n%world = world name of arena\n%max = max players per arena"),
			new Input("§aSign Placeholder 1", "Sign Text", $arena->statusLine1),
			new Input("§aSign Placeholder 2", "Sign Text", $arena->statusLine2),
			new Input("§aSign Placeholder 3", "Sign Text", $arena->statusLine3),
			new Input("§aSign Placeholder 4", "Sign Text", $arena->statusLine4),
		], function(Player $player, CustomFormResponse $response) use ($arena): void{
			$arena->setConfig($arena->getConfigManager()
				->setStatusLine($response->getInput()->getValue(), 1)
				->setStatusLine($response->getInput()->getValue(), 2)
				->setStatusLine($response->getInput()->getValue(), 3)
				->setStatusLine($response->getInput()->getValue(), 4), true);

			$player->sendMessage(TextFormat::GREEN . "Successfully updated sign lines for " . TextFormat::YELLOW . $arena->getMapName());

			$this->cleanupEvent($player);
		}, function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		$p->sendForm($form);
	}

	public function setupSpawn(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage("You can now setup the arena spawn positions, use the blaze rod and break a block to set the position, changes made in this world will be discarded.");

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::SET_SPAWN_COORDINATES;
		});
	}

	public function setupSpectator(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage("You can now setup the arena spectator position, use the blaze rod and break a block to set the position, changes made in this world will be discarded.");

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::SET_SPECTATOR_COORDINATES;
		});
	}

	public function teleportWorld(Player $player, ArenaImpl $arena): void{
		$this->performWorldCopy($arena, function(Level $level) use ($player, $arena): void{
			$this->setMagicWand($player);

			$player->teleport($level->getSpawnLocation());
			$player->sendMessage("You can now edit this world safely, any changes in this world will be saved.");

			$this->arenaSetup[$player->getName()] = $arena;
			$this->blockEvent[$player->getName()] = self::TELEPORT_TO_WORLD;
		});
	}

	public function setupJoinSign(Player $player, ArenaImpl $arena): void{
		$this->setMagicWand($player);

		$player->sendMessage("You can now setup the arena spectator position, use the blaze rod and break a block to set the position.");

		$this->arenaSetup[$player->getName()] = $arena;
		$this->blockEvent[$player->getName()] = self::SET_JOIN_SIGN_COORDINATES;
	}

	public function setupNPCCoordinates(Player $player): void{
		$this->setMagicWand($player);

		$player->sendMessage("You can now setup the NPC position, use the blaze rod and break a block to set the position.");

		$this->blockEvent[$player->getName()] = self::SET_NPC_COORDINATES;
	}

	private function deleteArena(Player $p, ArenaImpl $data): void{
		$form = new ModalForm("", "§cAre you sure to perform this action? Deleting an arena will delete your arena config and your world!",
			function(Player $player, bool $response) use ($data): void{
				$this->cleanupEvent($player);

				if(!$response) return;

				$this->plugin->getArenaManager()->deleteArena($data);

				$player->sendMessage(str_replace("{ARENA}", $data->getMapName(), $this->plugin->getMsg($player, 'arena-delete')));
			}, "§cDelete", "Cancel");

		$p->sendForm($form);
	}

	private function setupScoreboard(Player $player, ArenaImpl $arena, int $id = -1): void{
		if($id === -1){
			$buttons = [
				new Button("Waiting display"),
				new Button("In game display"),
				new Button("Ending game display"),
				new Button("Spectator display"),
				new Button("Exit", new Image("textures/blocks/barrier", Image::TYPE_PATH)),
			];

			$form = new MenuForm("§aChoose your arena first.", "", $buttons, function(Player $player, Button $selected) use ($buttons, $arena): void{
				$selectedButton = $selected->getValue();
				if(!isset($buttons[$selectedButton])){
					$this->cleanupEvent($player);

					return;
				}

				if($selectedButton === 4){
					$player->sendMessage(TextFormat::RED . "Exited scoreboard setup");
					$this->cleanupEvent($player);
				}else{
					$this->setupScoreboard($player, $arena, $selectedButton);
				}
			}, function(Player $player): void{
				$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

				$this->cleanupEvent($player);
			});

			$player->sendForm($form);

			return;
		}

		$configPath = $this->plugin->getDataFolder() . "scoreboards/" . $arena->getMapName() . ".yml";
		if(!is_file($configPath)){
			file_put_contents($configPath, $this->plugin->getResource("scoreboard.yml"));
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
				$player->sendMessage('The id you have requested is invalid, unable to perform this command');
				$this->cleanupEvent($player);

				return;
		}

		$elements = array_merge([new Label([
			"This section will be used during waiting state.",
			"This section will be used when the arena has started but without any teams.",
			"This section will be used when the game has finished.",
			"This section will be used the player has died.",
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

			$player->sendMessage(Settings::$prefix . TextFormat::GREEN . "Successfully setup the arena scoreboard configuration.");
		}, function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, 'panel-cancelled'));

			$this->cleanupEvent($player);
		});

		$player->sendForm($form);
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

		Server::getInstance()->getAsyncPool()->submitTask($task);
	}

	/**
	 * Show to player the panel cages.
	 * Decide their own private spawn pedestals
	 *
	 * @param Player $player
	 */
	public function showChooseCage(Player $player): void{
		$this->plugin->getDatabase()->getPlayerData($player->getName(), function(PlayerData $pd) use ($player){
			$form = new MenuForm("§cChoose Your Cage", "§aVarieties of cages available!");

			$cages = [];
			foreach($this->plugin->getCage()->getCages() as $cage){
				if((is_array($pd->cages) && !in_array(strtolower($cage->getCageName()), $pd->cages)) && $cage->getPrice() !== 0){
					$form->append("§8" . $cage->getCageName() . "\n§e[Price $" . $cage->getPrice() . "]");
				}else{
					$form->append("§8" . $cage->getCageName() . "\n§aBought");
				}
				$cages[] = $cage;
			}

			$form->setOnSubmit(function(Player $player, Button $selected) use ($cages): void{
				$this->plugin->getCage()->setPlayerCage($player, $cages[$selected->getValue()]);
			});

			$player->sendForm($form);
		});
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
						$player->sendMessage("You are not in the right arena world, teleport back to level {$arena->getLevelName()}");
						break;
					}

					$mode = $this->spawnCache[$player->getName()] ?? 1;

					if($mode <= $arena->getMaxPlayer()){
						$config->setSpawnPosition([$block->getX(), $block->getY() + 1, $block->getZ()], $mode);

						$player->sendMessage(str_replace("{COUNT}", (string)$mode, $this->plugin->getMsg($player, 'panel-spawn-pos')));

						if($mode === $arena->getMaxPlayer()){
							$player->sendMessage($this->plugin->getMsg($player, "panel-spawn-set"));
							$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

							$this->cleanupEvent($player, true);
							break;
						}

						$this->spawnCache[$player->getName()] = ++$mode;
					}
					break;
				case self::SET_SPECTATOR_COORDINATES:
					if($arena->getLevelName() !== $player->getLevel()->getFolderName()){
						$player->sendMessage("You are not in the right arena world, teleport back to level {$arena->getLevelName()}");
						break;
					}

					$config->setSpecSpawn($block->getX(), $block->getY(), $block->getZ());

					$player->sendMessage($this->plugin->getMsg($player, 'panel-join-spect'));
					$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

					$this->cleanupEvent($player, true);
					break;
				case self::SET_JOIN_SIGN_COORDINATES:
					if($this->plugin->getArenaManager()->getPlayerArena($player) !== null){
						$player->sendMessage("You cannot set arena join sign in the arena world!");
						break;
					}

					$config->setJoinSign($block->getX(), $block->getY(), $block->getZ(), $block->level->getFolderName());

					$player->sendMessage($this->plugin->getMsg($player, 'panel-join-sign'));

					$this->cleanupEvent($player);
					break;
				case self::SET_NPC_COORDINATES:
					if($this->plugin->getArenaManager()->getPlayerArena($player) !== null){
						$player->sendMessage("You cannot set arena join sign in the arena world!");
						break;
					}

					$config = new Config($this->plugin->getDataFolder() . "npc.yml", Config::YAML);
					$mode = $this->spawnCache[$player->getName()] ?? 1;

					if($mode <= 3){
						$y = $block instanceof Slab ? 0.5 : 1;

						$config->set("npc-$mode", [$block->getX() + .5, $block->getY() + $y, $block->getZ() + .5, $block->level->getFolderName()]);
						$config->save();

						$player->sendMessage(str_replace("{COUNT}", (string)$mode, $this->plugin->getMsg($player, 'panel-spawn-pos')));

						$this->spawnCache[$player->getName()] = ++$mode;
					}else{
						$player->sendMessage($this->plugin->getMsg($player, "panel-spawn-set"));
						$player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);

						$this->cleanupEvent($player);
					}
					break;
				case self::TELEPORT_TO_WORLD:
					$player->sendMessage(Settings::$prefix . "Teleporting you back to main world.");

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

					Server::getInstance()->getAsyncPool()->submitTask($task);
			}

			$event->setCancelled();
		}
	}

	private function cleanupEvent(Player $player, bool $cleanWorld = false): void{
		$arena = $this->arenaSetup[$player->getName()] ?? null;
		if($arena !== null){
			$arena->setConfig($arena->getConfigManager()->saveArena(), true);
			$arena->setFlags(ArenaImpl::ARENA_IN_SETUP_MODE, false);

			if($cleanWorld){
				$level = Server::getInstance()->getLevelByName($arena->getLevelName());
				if($level !== null) Server::getInstance()->unloadLevel($level, true);

				$task = new AsyncDirectoryDelete([Server::getInstance()->getDataPath() . "worlds/" . $arena->getLevelName()]);
				Server::getInstance()->getAsyncPool()->submitTask($task);
			}
		}

		if(isset($this->lastHoldIndex[$player->getName()])){
			$holdIndex = $this->lastHoldIndex[$player->getName()][0];
			$lastItem = $this->lastHoldIndex[$player->getName()][1];

			$player->getInventory()->setItem(0, $lastItem);
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