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

namespace larryTheCoder\panel;

use larryTheCoder\arena\api\ArenaState;
use larryTheCoder\arena\Arena;
use larryTheCoder\forms\CustomForm;
use larryTheCoder\forms\CustomFormResponse;
use larryTheCoder\forms\elements\{Button, Dropdown, Input, Label, Slider, Toggle};
use larryTheCoder\forms\MenuForm;
use larryTheCoder\forms\ModalForm;
use larryTheCoder\player\PlayerData;
use larryTheCoder\SkyWarsPE;
use larryTheCoder\task\NPCValidationTask;
use larryTheCoder\utils\{ConfigManager, Utils};
use pocketmine\{block\Slab, Player, Server, utils\TextFormat};
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\item\{BlazeRod, Item};
use pocketmine\utils\Config;
use RuntimeException;

/**
 * Implementation of a callable-based skywars interface, no more events-styled
 * burden in case there is a new feature that is going to be implemented in the future.
 *
 * Class FormPanel
 * @package larryTheCoder\panel
 */
class FormPanel implements Listener {

	/** @var SkyWarsPE */
	private $plugin;
	/** @var SkyWarsData[] */
	private $temporaryData = [];
	/** @var array */
	private $actions = [];
	/** @var int[] */
	private $mode = [];
	/** @var array */
	private $lastHoldIndex = [];

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;

		try{
			$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		}catch(\Throwable $e){
			throw new RuntimeException("Unable to register events correctly", 0, $e);
		}
	}

	/**
	 * @param Player $player
	 * @param Arena $arena
	 */
	public function showSpectatorPanel(Player $player, Arena $arena){
		$form = new MenuForm(TextFormat::BLUE . "Select Player Name");

		$players = [];
		foreach($arena->getPlayers() as $inGame){
			$form->append(new Button($inGame->getName()));
			$players[] = $inGame->getName();
		}

		$form->setText("Select a player to spectate");
		$form->setOnSubmit(function(Player $player, Button $selected) use ($arena): void{
			// Do not attempt to do anything if the arena is no longer running.
			// Or the player is no longer in arena
			if($arena->getStatus() !== ArenaState::STATE_ARENA_RUNNING || !$arena->isInArena($player)){
				$player->sendMessage(TextFormat::RED . "You are no longer in the arena.");

				return;
			}

			$target = $arena->getOriginPlayer($selected->getValue());
			if($target === null){
				$player->sendMessage(TextFormat::RED . "That player is no longer in the arena.");

				return;
			}
			$player->teleport($target);
		});
		$form->setOnClose(function(Player $player): void{
			$player->sendMessage($this->plugin->getMsg($player, "panel-cancelled"));
		});

		$player->sendForm($form);
	}

	/**
	 * Shows the current player stats in this game, this function is a callable
	 * based FormAPI and you know what it is written here...
	 *
	 * @param Player $player
	 */
	public function showStatsPanel(Player $player){
		// Checked and worked.
		$this->plugin->getDatabase()->getPlayerData($player->getName(), function(PlayerData $result) use ($player){
			$form = new CustomForm("§a{$result->player}'s stats",
				function(Player $player, CustomFormResponse $response): void{
				},
				function(Player $player): void{
				});

			$form->append(new Label("§6Name: §f" . $result->player),
				new Label("§6Kills: §f" . $result->kill),
				new Label("§6Deaths: §f" . $result->death),
				new Label("§6Wins: §f" . $result->wins),
				new Label("§6Lost: §f" . $result->lost)
			);

			$player->sendForm($form);
		});
	}

	/**
	 * The function that handles player arena creation, notifies player after he/she
	 * has successfully created the arenas.
	 *
	 * @param Player $player
	 */
	public function setupArena(Player $player){
		// Checked and worked.
		$form = new CustomForm("§5SkyWars Setup.");

		$files = [];
		# Check if there is ANOTHER ARENA is using this world
		$worldPath = Server::getInstance()->getDataPath() . 'worlds/';
		foreach(scandir($worldPath) as $file){
			if($file === "." || $file === ".."){
				continue;
			}
			if(strtolower(Server::getInstance()->getDefaultLevel()->getFolderName()) === strtolower($file) || !is_dir($worldPath . $file)){
				continue;
			}

			foreach($this->plugin->getArenaManager()->getArenas() as $arena){
				if($arena->getLevel() === null) continue; // Iterate to next arena.
				if(strtolower($arena->getLevel()->getFolderName()) === strtolower($file)){
					continue 2;
				}
			}

			$files[] = $file;
		}

		if(empty($files)){
			$player->sendMessage($this->plugin->getMsg($player, "no-world"));

			return;
		}

		$form->append(new Input("§6The name of your Arena.", "Donkey Island"),
			new Dropdown("§6Select your Arena level.", $files),
			new Slider("§eMaximum players", 4, 40),
			new Slider("§eMinimum players", 2, 40),
			new Toggle("§7Spectator mode", true),
			new Toggle("§7Start on full", true)
		);

		$form->setOnSubmit(function(Player $player, CustomFormResponse $response): void{
			$data = new SkyWarsData();

			$responseCustom = $response;
			$data->arenaName = $responseCustom->getInput()->getValue();
			$data->arenaLevel = $responseCustom->getDropdown()->getSelectedOption();
			$data->maxPlayer = $responseCustom->getSlider()->getValue();
			$data->minPlayer = $responseCustom->getSlider()->getValue();
			$data->spectator = $responseCustom->getToggle()->getValue();
			$data->startWhenFull = $responseCustom->getToggle()->getValue();
			if($this->plugin->getArenaManager()->arenaExist($data->arenaName)){
				$player->sendMessage($this->plugin->getMsg($player, 'arena-exists'));

				return;
			}

			if(empty($data->arenaLevel)){
				$player->sendMessage($this->plugin->getMsg($player, 'panel-low-arguments'));

				return;
			}

			file_put_contents($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml", $this->plugin->getResource('arenas/default.yml'));

			$a = new ConfigManager($data->arenaName, $this->plugin);
			$a->setArenaWorld($data->arenaLevel);
			$a->setArenaName($data->arenaName);
			$a->enableSpectator($data->spectator);
			$a->setPlayersCount($data->maxPlayer > $data->minPlayer ? $data->maxPlayer : $data->minPlayer, $data->minPlayer);
			$a->startOnFull($data->startWhenFull);
			$a->applyFullChanges();

			$form = new ModalForm("", "§aYou may need to setup the spawn position so system could enable the arena mode faster.",
				function(Player $player, bool $response) use ($data): void{
					if($response) $this->setupSpawn($player, $data);
				}, "Setup arena spawn.", "§cSetup later.");

			$player->sendForm($form);
		});
		$form->setOnClose(function(Player $pl): void{
			$pl->sendMessage($this->plugin->getMsg($pl, 'panel-cancelled'));
		});

		$player->sendForm($form);
	}

	private function setupSpawn(Player $player, SkyWarsData $arena = null){
		Utils::loadFirst($arena->arenaLevel);

		$arenaConfig = new ConfigManager($arena->arenaName, $this->plugin);
		$arenaConfig->resetSpawnPedestal();

		$this->temporaryData[$player->getName()] = $arena;
		$this->actions[strtolower($player->getName())]['type'] = 'spawnpos';

		$level = $this->plugin->getServer()->getLevelByName($arena->arenaLevel);
		$player->teleport($level->getSpawnLocation());
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function setMagicWand(Player $p){
		$this->lastHoldIndex[$p->getName()] = [$p->getInventory()->getHeldItemIndex(), $p->getInventory()->getHotbarSlotItem(0)];

		$p->setGamemode(1);
		$p->getInventory()->setHeldItemIndex(0);
		$p->getInventory()->setItemInHand(new BlazeRod());
	}

	private function cleanupArray(Player $player, bool $resetWorld = false){
		if(isset($this->temporaryData[$player->getName()])){
			$this->plugin->getArenaManager()->reloadArena($this->temporaryData[$player->getName()]->arenaName, $resetWorld);
			unset($this->temporaryData[$player->getName()]);
		}

		// Now, its more reliable.
		if(isset($this->lastHoldIndex[$player->getName()])){
			$holdIndex = $this->lastHoldIndex[$player->getName()][0];
			$lastItem = $this->lastHoldIndex[$player->getName()][1];
			$player->getInventory()->setItem(0, $lastItem);
			$player->getInventory()->setHeldItemIndex($holdIndex);
			unset($this->lastHoldIndex[$player->getName()]);
		}
	}

	/**
	 * This function handle the settings for arena(s)
	 *
	 * @param Player $player
	 */
	public function showSettingPanel(Player $player){
		$form = new MenuForm("§aChoose your arena first.");
		foreach($this->plugin->getArenaManager()->getArenas() as $arena){
			$form->append(ucwords($arena->getArenaName()));
		}

		$form->setOnSubmit(function(Player $player, Button $selected): void{
			$arena = $this->plugin->getArenaManager()->getArenaByInt($selected->getValue());
			$data = $this->toData($arena);

			$form = new MenuForm("Setup for arena {$arena->getArenaName()}");
			$form->append(
				"Setup Arena Spawn",            // Arena Spawn
				"Setup Spectator Spawn",        // Spectator spawn
				"Setup Arena Behaviour",        // (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
				"Set Join Sign Behaviour",      // (Text) (Interval) (enable-interval)
				"Set Join Sign Location",       // Sign location teleportation.
				"Edit this world",              // Editing the world.
				TextFormat::RED . "Delete this arena"
			);

			$form->setOnSubmit(function(Player $player, Button $selected) use ($data): void{
				switch($selected->getValue()){
					case 0:
						$this->setupSpawn($player, $data);
						break;
					case 1:
						$this->setupSpectate($player, $data);
						break;
					case 2:
						$this->arenaBehaviour($player, $data);
						break;
					case 3:
						$this->joinSignBehaviour($player, $data);
						break;
					case 4:
						$this->joinSignSetup($player, $data);
						break;
					case 5:
						$this->teleportWorld($player, $data);
						break;
					case 6:
						$this->deleteSure($player, $data);
						break;
				}
			});

			$player->sendForm($form);
		});
		$form->setOnClose(function(Player $pl): void{
			$pl->sendMessage($this->plugin->getMsg($pl, 'panel-cancelled'));
		});

		$player->sendForm($form);
	}

	private function toData(Arena $arena): SkyWarsData{
		$data = new SkyWarsData();
		$data->arena = $arena;
		$data->maxPlayer = $arena->maximumPlayers;
		$data->minPlayer = $arena->minimumPlayers;
		$data->arenaLevel = $arena->arenaWorld;
		$data->arenaName = $arena->getArenaName();
		$data->spectator = $arena->data["arena"]["spectator-mode"];
		$data->startWhenFull = $arena->data["arena"]["start-when-full"];
		$data->graceTimer = $arena->data["arena"]["grace-time"];
		$data->enabled = $arena->data["enabled"];
		$data->line1 = str_replace("&", "§", $arena->data['signs']['status-line-1']);
		$data->line2 = str_replace("&", "§", $arena->data['signs']['status-line-2']);
		$data->line3 = str_replace("&", "§", $arena->data['signs']['status-line-3']);
		$data->line4 = str_replace("&", "§", $arena->data['signs']['status-line-4']);

		return $data;
	}

	private function setupSpectate(Player $player, SkyWarsData $arena){
		Utils::loadFirst($arena->arenaLevel);

		$arenaConfig = new ConfigManager($arena->arenaName, $this->plugin);
		$arenaConfig->resetSpawnPedestal();

		$this->temporaryData[$player->getName()] = $arena;
		$this->actions[strtolower($player->getName())]['type'] = 'setspecspawn';

		$level = $this->plugin->getServer()->getLevelByName($arena->arenaLevel);
		$player->teleport($level->getSpawnLocation());
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function arenaBehaviour(Player $player, SkyWarsData $arena){
		// (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
		$form = new CustomForm("Arena settings.",
			function(Player $player, CustomFormResponse $response) use ($arena): void{
				$enable = $response->getToggle()->getValue();
				$graceTimer = $response->getSlider()->getValue();
				$spectatorMode = $response->getToggle()->getValue();
				$maxPlayer = $response->getSlider()->getValue();
				$minPlayer = $response->getSlider()->getValue();
				$startWhenFull = $response->getToggle()->getValue();
				# Get the config

				$a = new ConfigManager($arena->arenaName, $this->plugin);
				$a->setEnable($enable);
				$a->setGraceTimer($graceTimer);
				$a->enableSpectator($spectatorMode);
				$a->setPlayersCount($maxPlayer > $minPlayer ? $maxPlayer : $minPlayer, $arena->minPlayer);
				$a->startOnFull($startWhenFull);
				$a->applyFullChanges();

				$player->sendMessage("Done!");
			},
			function(Player $pl): void{
				$pl->sendMessage($this->plugin->getMsg($pl, 'panel-cancelled'));
			});

		$form->append(
			new Toggle("§eEnable the arena?", $arena->enabled),
			new Slider("§eSet Grace Timer", 0, 30, 1, $arena->graceTimer),
			new Toggle("§eEnable Spectator Mode?", $arena->spectator),
			new Slider("§eMaximum players to be in arena", 0, 50, 1, $arena->maxPlayer),
			new Slider("§eMinimum players to be in arena", 0, 50, 1, $arena->minPlayer),
			new Toggle("§eStart when full", $arena->startWhenFull));

		$player->sendForm($form);
	}

	private function joinSignBehaviour(Player $p, SkyWarsData $data){
		$form = new CustomForm("§eForm Behaviour Setup");

		$form->setTitle("§eForm Behaviour Setup");
		$form->append(
			new Label("§aWelcome to sign Behaviour Setup. First before you doing anything, you may need to know these"),
			new Label("§eStatus lines\n&a &b &c = you can use color with &\n%alive = amount of in-game players\n%dead = amount of dead players\n%status = game status\n%world = world name of arena\n%max = max players per arena"),
			new Input("§aSign Placeholder 1", "Sign Text", $data->line1),
			new Input("§aSign Placeholder 2", "Sign Text", $data->line2),
			new Input("§aSign Placeholder 3", "Sign Text", $data->line3),
			new Input("§aSign Placeholder 4", "Sign Text", $data->line4)
		);

		$form->setOnSubmit(function(Player $player, CustomFormResponse $response) use ($data): void{
			$a = new ConfigManager($data->arenaName, $this->plugin);

			$a->setStatusLine($response->getInput()->getValue(), 1);
			$a->setStatusLine($response->getInput()->getValue(), 2);
			$a->setStatusLine($response->getInput()->getValue(), 3);
			$a->setStatusLine($response->getInput()->getValue(), 4);

			$player->sendMessage("Done!");
		});
		$form->setOnClose(function(Player $pl): void{
			$pl->sendMessage($this->plugin->getMsg($pl, 'panel-cancelled'));
		});

		$p->sendForm($form);
	}

	/**
	 * Show to player the panel cages.
	 * Decide their own private spawn pedestals
	 *
	 * @param Player $player
	 */
	public function showChooseCage(Player $player){
		$this->plugin->getDatabase()->getPlayerData($player->getName(), function(PlayerData $pd) use ($player){
			$form = new MenuForm("§cChoose Your Cage");
			$form->setText("§aVarieties of cages available!");

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

	private function joinSignSetup(Player $player, SkyWarsData $data){
		Utils::loadFirst($data->arenaLevel);

		$this->temporaryData[$player->getName()] = $data;
		$this->actions[strtolower($player->getName())]['type'] = 'setjoinsign';
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function teleportWorld(Player $p, SkyWarsData $arena){
		$p->setGamemode(1);

		$this->temporaryData[$p->getName()] = $arena;
		$this->actions[strtolower($p->getName())]['WORLD'] = "EDIT-WORLD";
		$p->sendMessage("You are now be able to edit the world now, best of luck");
		$p->sendMessage("Use blaze rod if you have finished editing the world.");

		$arena->arena->performEdit(ArenaState::STARTING);

		$level = $this->plugin->getServer()->getLevelByName($arena->arenaLevel);
		$p->teleport($level->getSpawnLocation());

		$p->getInventory()->setHeldItemIndex(0);
		$p->getInventory()->clearAll(); // Perhaps
	}

	private function deleteSure(Player $p, SkyWarsData $data){
		$form = new ModalForm("", "§cAre you sure that you want to delete this arena? While you deleting this arena, your world wont be effected.",
			function(Player $player, bool $response) use ($data): void{
				if(!$response) return;

				unlink($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml");
				$this->plugin->getArenaManager()->deleteArena($data->arenaName);
				$player->sendMessage(str_replace("{ARENA}", $data->arenaName, $this->plugin->getMsg($player, 'arena-delete')));
			},
			"§cDelete", "Cancel");

		$p->sendForm($form);
	}

	/**
	 * @param BlockBreakEvent $e
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $e){
		$p = $e->getPlayer();
		if(isset($this->temporaryData[$p->getName()]) && isset($this->actions[strtolower($p->getName())]['type'])){
			if($e->getItem()->getId() === Item::BLAZE_ROD){
				if(!isset($this->mode[strtolower($p->getName())])) $this->mode[strtolower($p->getName())] = 1;

				$e->setCancelled(true);
				$b = $e->getBlock();
				$arena = new ConfigManager($this->temporaryData[$p->getName()]->arenaName, $this->plugin);

				if($this->actions[strtolower($p->getName())]['type'] == "setjoinsign"){
					$arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
					$p->sendMessage($this->plugin->getMsg($p, 'panel-join-sign'));
					unset($this->actions[strtolower($p->getName())]['type']);

					$this->cleanupArray($p);

					return;
				}

				if($this->actions[strtolower($p->getName())]['type'] == "setspecspawn"){
					$arena->setSpecSpawn($b->x, $b->y, $b->z);

					$p->sendMessage($this->plugin->getMsg($p, 'panel-join-spect'));
					$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
					$p->teleport($spawn, 0, 0);
					unset($this->actions[strtolower($p->getName())]['type']);

					$this->cleanupArray($p);

					return;
				}

				if($this->actions[strtolower($p->getName())]['type'] == "spawnpos"){
					if($this->mode[strtolower($p->getName())] >= 1 && $this->mode[strtolower($p->getName())] <= $arena->arena->getNested('arena.max-players')){
						$arena->setSpawnPosition([$b->getX(), $b->getY() + 1, $b->getZ()], $this->mode[strtolower($p->getName())]);

						$p->sendMessage(str_replace("{COUNT}", $this->mode[strtolower($p->getName())], $this->plugin->getMsg($p, 'panel-spawn-pos')));
						$this->mode[strtolower($p->getName())]++;
					}
					if($this->mode[strtolower($p->getName())] === $arena->arena->getNested('arena.max-players') + 1){
						$p->sendMessage($this->plugin->getMsg($p, "panel-spawn-set"));
						$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
						$p->teleport($spawn, 0, 0);
						unset($this->mode[strtolower($p->getName())]);
						unset($this->actions[strtolower($p->getName())]['type']);

						$this->cleanupArray($p);
					}
					$arena->arena->save();

					return;
				}
			}
		}

		if(isset($this->actions[strtolower($p->getName())]['NPC'])
			&& $e->getItem()->getId() === Item::BLAZE_ROD){
			$e->setCancelled(true);
			$b = $e->getBlock();
			$cfg = new Config($this->plugin->getDataFolder() . "npc.yml", Config::YAML);
			if($this->mode[strtolower($p->getName())] >= 1 && $this->mode[strtolower($p->getName())] <= 3){
				$y = 1;
				if($b instanceof Slab){
					$y = 0.5;
				}
				$cfg->set("npc-{$this->mode[strtolower($p->getName())]}", [$b->getX() + 0.5, $b->getY() + $y, $b->getZ() + 0.5, $b->getLevel()->getName()]);
				$p->sendMessage(str_replace("{COUNT}", $this->mode[strtolower($p->getName())], $this->plugin->getMsg($p, 'panel-spawn-pos')));
				$this->mode[strtolower($p->getName())]++;
			}
			if($this->mode[strtolower($p->getName())] === 4){
				unset($this->mode[strtolower($p->getName())]);
				unset($this->actions[strtolower($p->getName())]['NPC']);
				$this->cleanupArray($p);
				NPCValidationTask::setChanged();
			}
			$cfg->save();
		}

		if(isset($this->actions[strtolower($p->getName())]['WORLD'])
			&& $e->getItem()->getId() === Item::BLAZE_ROD){
			$e->setCancelled(true);

			$p->sendMessage($this->plugin->getPrefix() . "Teleporting you back to main world.");

			$level = $p->getLevel();
			$level->save(true);

			$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
			$p->teleport($spawn, 0, 0);

			$this->temporaryData[$p->getName()]->arena->performEdit(ArenaState::FINISHED);

			unset($this->actions[strtolower($p->getName())]['WORLD']);
			$this->cleanupArray($p, true);
		}
	}

	public function showNPCConfiguration(Player $p){
		$p->setGamemode(1);

		$this->actions[strtolower($p->getName())]['NPC'] = "SETUP-NPC";
		$this->mode[strtolower($p->getName())] = 1;
		$p->sendMessage($this->plugin->getMsg($p, 'panel-spawn-wand'));
		$this->setMagicWand($p);
	}

}