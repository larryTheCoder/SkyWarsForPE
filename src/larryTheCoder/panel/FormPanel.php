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


use larryTheCoder\arena\Arena;
use larryTheCoder\formAPI\{event\FormRespondedEvent,
	response\FormResponseCustom,
	response\FormResponseModal,
	response\FormResponseSimple};
use larryTheCoder\SkyWarsPE;
use larryTheCoder\task\NPCValidationTask;
use larryTheCoder\utils\{ConfigManager, Utils};
use pocketmine\{block\Slab, Player, Server};
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\item\{BlazeRod, Item};
use pocketmine\utils\Config;

/**
 * Panel class that calls the Form to spawn into player,
 * This class has been used to make an ease contact from server
 * to player, This is the future of SW-Setup-Solution
 *
 * Class FormPanel
 * @package larryTheCoder\panel
 */
class FormPanel implements Listener {

	const PANEL_SETUP = 0;
	const PANEL_SPAWN_SETUP = 1;
	const PANEL_SET_SPAWN = 2;
	const PANEL_SETTINGS_CHOOSE = 3;
	const PANEL_SETTINGS_ARENA = 4;
	const PANEL_SETUP_BEHAVIOUR = 5;
	const PANEL_SIGN_BEHAVIOUR = 6;
	const PANEL_DELETE_ARENA = 7;
	const PANEL_SPECTATOR_SET = 8;
	const PANEL_CHOSE_CAGE = 9;

	/** @var SkyWarsPE */
	private $plugin;
	/** @var array */
	private $forms = [];
	/** @var SkyWarsData[] */
	private $data = [];
	/** @var array */
	private $setters = [];
	/** @var int[] */
	private $mode = [];
	/** @var Player[]|string[] */
	private $command = [];
	/** @var array */
	private $lastHoldIndex;

	public function __construct(SkyWarsPE $plugin){
		$this->plugin = $plugin;

		try{
			$plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
		}catch(\Throwable $e){
		}
	}

	/**
	 * @param Player $player
	 * @param Arena $arena
	 */
	public function showSpectatorPanel(Player $player, Arena $arena){
		$form = $this->plugin->formAPI->createSimpleForm();

		$players = [];
		foreach($arena->players as $inGame){
			$path = $this->plugin->getDataFolder() . 'image/' . strtolower($inGame->getName()) . ".png";
			$form->addButton($inGame->getName(), 0, $path);
			$players[] = $inGame->getName();
		}
		$path = $this->plugin->getDataFolder() . 'image/' . strtolower($player->getName()) . ".png";
		$form->addButton($player->getName(), 0, $path);

		$form->sendToPlayer($player);
		$this->forms[$form->getId()] = self::PANEL_SPECTATOR_SET;
		$this->command[$player->getName()] = $players;
	}

	public function showStatsPanel(Player $player){
		$db = $this->plugin->getDatabase()->getPlayerData($player->getName());
		$formAPI = $this->plugin->formAPI->createCustomForm();

		$formAPI->addLabel("§6Name: §f" . $db->player);
		$formAPI->addLabel("§6Kills: §f" . $db->kill);
		$formAPI->addLabel("§6Deaths: §f" . $db->death);
		$formAPI->addLabel("§6Wins: §f" . $db->wins);
		$formAPI->addLabel("§6Lost: §f" . $db->lost);

		$formAPI->sendToPlayer($player);
	}

	/**
	 * Create arena and setup spawn position.
	 *
	 * @param Player $player
	 */
	public function setupArena(Player $player){
		$form = $this->plugin->formAPI->createCustomForm();
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
				if(strtolower($arena->getArenaLevel()->getFolderName()) === strtolower($file)){
					continue 2;
				}
			}

			$files[] = $file;
		}

		if(empty($files)){
			$player->sendMessage($this->plugin->getMsg($player, "no-world"));

			return;
		}

		$form->setTitle("§5SkyWars Setup.");
		# What the player want to name the arena
		$form->addInput("§6The name of your Arena.", "Donkey Island");
		# What is the level for input 'Arena Name'
		$form->addDropdown("§6Select your Arena level.", $files);
		# How much player do that this arena need
		$form->addSlider("§eMaximum players", 4, 40);
		$form->addSlider("§eMinimum players", 2, 40);
		# Ask if they want these enabled
		$form->addToggle("§7Spectator mode", true);
		$form->addToggle("§7Start on full", true);

		$form->sendToPlayer($player);
		$this->forms[$form->getId()] = FormPanel::PANEL_SETUP;
	}

	/**
	 * @param FormRespondedEvent $event
	 * @priority MONITOR
	 */
	public function onResponse(FormRespondedEvent $event){
		$id = $event->getId();
		$p = $event->getPlayer();
		$response = $event->getResponse();
		if(isset($this->forms[$id])){
			$command = $this->forms[$id];
			unset($this->forms[$id]);
			switch($command){
				case FormPanel::PANEL_SETUP:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, 'panel-cancelled'));
						break;
					}
					$data = new SkyWarsData();
					/** @var FormResponseCustom $responseCustom */
					$responseCustom = $response;
					$data->arenaName = str_replace(" ", "-", $responseCustom->getInputResponse(0));
					$data->arenaLevel = $responseCustom->getDropdownResponse(1)->getElementContent();
					$data->maxPlayer = $responseCustom->getSliderResponse(2);
					$data->minPlayer = $responseCustom->getSliderResponse(3);
					$data->spectator = $responseCustom->getToggleResponse(4);
					$data->startWhenFull = $responseCustom->getToggleResponse(5);
					$this->data[$p->getName()] = $data;
					if($this->plugin->getArenaManager()->arenaExist($data->arenaName)){
						$p->sendMessage($this->plugin->getMsg($p, 'arena-exists'));
						break;
					}
					if(empty($data->arenaLevel)){
						$p->sendMessage($this->plugin->getMsg($p, 'panel-low-arguments'));
						break;
					}

					file_put_contents($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml", $this->plugin->getResource('arenas/default.yml'));
					$a = new Config($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml", Config::YAML);
					$a->reload();
					$a->set('arena-name', $responseCustom->getInputResponse(0));
					$a->setNested('arena.arena_world', $data->arenaLevel);
					$a->setNested('arena.spectator_mode', $data->spectator);
					$a->setNested('arena.max_players', $data->maxPlayer > $data->minPlayer ? $data->maxPlayer : $data->minPlayer);
					$a->setNested('arena.min_players', $data->minPlayer);
					$a->setNested('arena.start_when_full', $data->startWhenFull);
					$a->save();

					$this->plugin->getArenaManager()->setArenaData($a, $data->arenaName);
					$this->setupSpawn($p);
					break;
				case FormPanel::PANEL_SPAWN_SETUP:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseModal $responseMo */
					$responseMo = $response;
					$buttonId = $responseMo->getClickedButtonId();
					if($buttonId === 0){
						$this->setupSpawn($p, $this->data[$p->getName()]);
					}else{
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
					}
					break;
				case FormPanel::PANEL_SETTINGS_CHOOSE:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseSimple $responseMo */
					$responseMo = $response;
					$buttonText = $responseMo->getClickedButtonId();
					$arena = $this->plugin->getArenaManager()->getArenaByInt($buttonText);
					if($arena === null){
						$p->sendMessage($this->plugin->getMsg($p, "arena-not-exist"));
						$this->cleanupArray($p);
						break;
					}
					$this->showSettingPanel($p, $this->toData($arena));
					break;
				case FormPanel::PANEL_SETTINGS_ARENA:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseSimple $responseSi */
					$responseSi = $response;
					$buttonId = $responseSi->getClickedButtonId();
					$data = $this->data[$p->getName()];
					switch($buttonId){
						case 0:
							$this->setupSpawn($p, $data);
							break;
						case 1:
							$this->setupSpecS($p, $data);
							break;
						case 2:
							$this->arenaBehaviour($p, $data);
							break;
						case 3:
							$this->joinSignBehaviour($p, $data);
							break;
						case 4:
							$this->joinSignSetup($p, $data);
							break;
						case 5:
							$this->teleportWorld($p);
							break;
						case 6:
							$this->deleteSure($p, false);
							break;
					}
					break;
				case FormPanel::PANEL_SETUP_BEHAVIOUR:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseCustom $responseCustom */
					$responseCustom = $response;
					$data = $this->data[$p->getName()];
					$enable = $responseCustom->getToggleResponse(0);
					$graceTimer = $responseCustom->getSliderResponse(1);
					$spectatorMode = $responseCustom->getToggleResponse(2);
					$maxTime = $responseCustom->getSliderResponse(3);
					$maxPlayer = $responseCustom->getSliderResponse(4);
					$minPlayer = $responseCustom->getSliderResponse(5);
					$startWhenFull = $responseCustom->getToggleResponse(6);
					# Get the config
					$a = new Config($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml", Config::YAML);
					$a->set("enabled", $enable);
					$a->setNested('arena.grace_time', $graceTimer);
					$a->setNested('arena.spectator_mode', $spectatorMode);
					$a->setNested('arena.max_players', $maxPlayer > $minPlayer ? $maxPlayer : $minPlayer);
					$a->setNested('arena.min_players', $data->minPlayer);
					$a->setNested('arena.start_when_full', $startWhenFull);
					$a->setNested('arena.max_game_time', $maxTime);
					$a->save();
					$this->plugin->getArenaManager()->setArenaData($a, $data->arenaName);
					$this->cleanupArray($p);
					break;
				case FormPanel::PANEL_SIGN_BEHAVIOUR:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseCustom $responseCustom */
					$responseCustom = $response;
					$data = $this->data[$p->getName()];
					$text1 = $responseCustom->getInputResponse(2);
					$text2 = $responseCustom->getInputResponse(3);
					$text3 = $responseCustom->getInputResponse(4);
					$text4 = $responseCustom->getInputResponse(5);

					$a = new Config($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml", Config::YAML);
					$a->setNested('signs.status_line_1', $text1);
					$a->setNested('signs.status_line_2', $text2);
					$a->setNested('signs.status_line_3', $text3);
					$a->setNested('signs.status_line_4', $text4);
					$this->plugin->getArenaManager()->setArenaData($a, $data->arenaName);
					$a->save();
					$this->cleanupArray($p);
					break;
				case FormPanel::PANEL_DELETE_ARENA:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseModal $responseMo */
					$responseMo = $response;
					$buttonId = $responseMo->getClickedButtonId();
					if($buttonId === 0){
						$this->deleteSure($p, true);
					}else{
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
					}
					break;
				case FormPanel::PANEL_SPECTATOR_SET:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseSimple $responseSi */
					$player = $this->command[$p->getName()];
					$responseSi = $response;
					$buttonId = $responseSi->getClickedButtonId();
					$p->teleport($player[$buttonId]);
					$this->cleanupArray($p);
					break;
				case FormPanel::PANEL_CHOSE_CAGE:
					if($response->closed){
						$p->sendMessage($this->plugin->getMsg($p, "panel-cancelled"));
						$this->cleanupArray($p);
						break;
					}
					/** @var FormResponseSimple $responseSi */
					$responseSi = $response;
					$cage = $this->command[$p->getName()][$responseSi->getClickedButtonId()];
					$button = $this->plugin->getCage()->getCages()[strtolower($cage)];
					$this->plugin->getCage()->setPlayerCage($p, $button);
					$this->cleanupArray($p);
					break;
				default:
					break;
			}
		}
	}

	private function setupSpawn(Player $player, SkyWarsData $arena = null){
		if($arena === null){
			$form = $this->plugin->formAPI->createModalForm();
			$form->setContent("§aYou may need to setup the spawn position so system could enable the arena mode faster.");
			$form->setButton1("Setup arena spawn.");
			$form->setButton2("§cSetup later.");
			$form->sendToPlayer($player);
			$this->forms[$form->getId()] = FormPanel::PANEL_SPAWN_SETUP;

			return;
		}
		Utils::loadFirst($arena->arenaLevel);
		$this->setters[strtolower($player->getName())]['type'] = 'spawnpos';
		$level = $this->plugin->getServer()->getLevelByName($arena->arenaLevel);
		$player->teleport($level->getSpawnLocation());
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function setMagicWand(Player $p){
		$this->lastHoldIndex[$p->getName()] = [$p->getInventory()->getHeldItemIndex(), $p->getInventory()->getHotbarSlotItem(0)];
		$p->getInventory()->setHeldItemIndex(0);
		$p->getInventory()->setItemInHand(new BlazeRod());
	}

	private function cleanupArray(Player $player){
		if(isset($this->data[$player->getName()])){
			$this->plugin->getArenaManager()->reloadArena($this->data[$player->getName()]->arenaName);
			unset($this->data[$player->getName()]);
		}
		if(isset($this->command[$player->getName()])){
			unset($this->command[$player->getName()]);
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
	 * @param SkyWarsData|null $arena
	 */
	public function showSettingPanel(Player $player, SkyWarsData $arena = null){
		$form = $this->plugin->formAPI->createSimpleForm();
		if($arena === null){
			$form->setContent("§aChoose your arena first.");
			foreach($this->plugin->getArenaManager()->getArenas() as $arena){
				$form->addButton(ucwords($arena->getArenaName()));
			}
			$form->sendToPlayer($player);
			$this->forms[$form->getId()] = FormPanel::PANEL_SETTINGS_CHOOSE;

			return;
		}
		$a = $this->plugin->getArenaManager()->getArena($arena->arenaName);
		if($a->getMode() >= Arena::ARENA_RUNNING || count(array_merge($a->players, $a->spec)) > 0){
			$player->sendMessage($this->plugin->getMsg($player, 'arena-running'));

			return;
		}
		$a->setup = true;

		$form->setContent("Setup for arena {$a->getArenaName()}");
		$form->addButton("Setup Arena Spawn"); // Arena Spawn
		$form->addButton("Setup Spectator Spawn"); // Spectator spawn
		// (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
		$form->addButton("Setup Arena Behaviour");
		$form->addButton("Set Join Sign Behaviour"); // (Text) (Interval) (enable-interval)
		$form->addButton("Set Join Sign Location");
		$form->addButton("Edit this world");
		$form->addButton("Delete this arena");

		$form->sendToPlayer($player);
		$this->forms[$form->getId()] = FormPanel::PANEL_SETTINGS_ARENA;
		$this->data[$player->getName()] = $arena;
	}

	private function toData(Arena $arena): SkyWarsData{
		$data = new SkyWarsData();
		$data->maxPlayer = $arena->getMaxPlayers();
		$data->minPlayer = $arena->getMinPlayers();
		$data->arenaLevel = $arena->getLevelName();
		$data->arenaName = $arena->getArenaName();
		$data->spectator = $arena->data["arena"]["spectator_mode"];
		$data->startWhenFull = $arena->data["arena"]["start_when_full"];
		$data->graceTimer = $arena->data["arena"]["grace_time"];
		$data->time = $arena->data["arena"]["max_game_time"];
		$data->enabled = $arena->data["enabled"];
		$data->line1 = str_replace("&", "§", $arena->data['signs']['status_line_1']);
		$data->line2 = str_replace("&", "§", $arena->data['signs']['status_line_2']);
		$data->line3 = str_replace("&", "§", $arena->data['signs']['status_line_3']);
		$data->line4 = str_replace("&", "§", $arena->data['signs']['status_line_4']);

		return $data;
	}

	private function setupSpecS(Player $player, SkyWarsData $arena = null){
		if($arena === null){
			$form = $this->plugin->formAPI->createSimpleForm();
			$form->setContent("§aChoose your arena first.");
			foreach($this->plugin->getArenaManager()->getArenas() as $arena){
				$form->addButton($arena->getArenaName());
			}
			$form->sendToPlayer($player);
			$this->forms[$form->getId()] = FormPanel::PANEL_SETTINGS_CHOOSE;

			return;
		}
		Utils::loadFirst($arena->arenaLevel);
		$this->setters[strtolower($player->getName())]['type'] = 'setspecspawn';
		$level = $this->plugin->getServer()->getLevelByName($arena->arenaLevel);
		$player->teleport($level->getSpawnLocation());
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function arenaBehaviour(Player $player, SkyWarsData $arena){
		// (Grace Timer) (Spectator Mode) (Time) (Enable) (Starting Time) (Max Player) (Min Player)
		$form = $this->plugin->formAPI->createCustomForm();

		$form->addToggle("§eEnable the arena?", $arena->enabled);
		$form->addSlider("§eSet Grace Timer", 0, 30, -1, $arena->graceTimer);
		$form->addToggle("§eEnable Spectator Mode?", $arena->spectator);
		$form->addSlider("§eSet max game time.", 0, 1000, -1, $arena->time);
		$form->addSlider("§eMaximum players to be in arena", 0, 50, -1, $arena->maxPlayer);
		$form->addSlider("§eMinimum players to be in arena", 0, 50, -1, $arena->minPlayer);
		$form->addToggle("§eStart when full", $arena->startWhenFull);

		$form->sendToPlayer($player);
		$this->forms[$form->getId()] = FormPanel::PANEL_SETUP_BEHAVIOUR;
	}

	private function joinSignBehaviour(Player $p, SkyWarsData $data){
		$form = $this->plugin->formAPI->createCustomForm();

		$form->setTitle("§eForm Behaviour Setup");
		$form->addLabel("§aWelcome to sign Behaviour Setup. First before you doing anything, you may need to know these");
		$form->addLabel("§eStatus lines\n&a &b &c = you can use color with &\n%alive = amount of in-game players\n%dead = amount of dead players\n%status = game status\n%world = world name of arena\n%max = max players per arena");
		$form->addInput("§aSign Placeholder 1", "Sign Text", $data->line1);
		$form->addInput("§aSign Placeholder 2", "Sign Text", $data->line2);
		$form->addInput("§aSign Placeholder 3", "Sign Text", $data->line3);
		$form->addInput("§aSign Placeholder 4", "Sign Text", $data->line4);

		$form->sendToPlayer($p);
		$this->forms[$form->getId()] = FormPanel::PANEL_SIGN_BEHAVIOUR;
	}

	/**
	 * Show to player the panel cages.
	 * Decide their own private spawn pedestals
	 *
	 * @param Player $player
	 */
	public function showChooseCage(Player $player){
		$pd = $this->plugin->getDatabase()->getPlayerData($player->getName());
		$form = $this->plugin->formAPI->createSimpleForm();
		$form->setTitle("§cChoose Your Cage");
		$form->setContent("§aVarieties of cages available!");

		$array = [];
		foreach($this->plugin->getCage()->getCages() as $cage){
			if(!in_array(strtolower($cage->getCageName()), $pd->cages) && $cage->getPrice() !== 0){
				$form->addButton("§8" . $cage->getCageName() . " §d§l[$" . $cage->getPrice() . "]");
			}else{
				$form->addButton("§8" . $cage->getCageName());
			}
			$array[] = $cage->getCageName();
		}

		$form->sendToPlayer($player);
		$this->command[$player->getName()] = $array;
		$this->forms[$form->getId()] = FormPanel::PANEL_CHOSE_CAGE;
	}

	private function joinSignSetup(Player $player, SkyWarsData $data){
		Utils::loadFirst($data->arenaLevel);
		$this->setters[strtolower($player->getName())]['type'] = 'setjoinsign';
		$player->sendMessage($this->plugin->getMsg($player, 'panel-spawn-wand'));
		$this->setMagicWand($player);
	}

	private function teleportWorld(Player $p){
		$p->setGamemode(1);
		$this->setters[strtolower($p->getName())]['WORLD'] = "EDIT-WORLD";
		$p->sendMessage("You may be able to edit the world now, good luck");
		$p->getInventory()->setHeldItemIndex(0);
		$p->getInventory()->clearAll(); // Perhaps
	}

	private function deleteSure(Player $p, bool $executed = false){
		if(!$executed){
			$form = $this->plugin->formAPI->createModalForm();
			$form->setContent("§cAre you sure that you want to delete this arena? While you deleting this arena, your world wont be effected.");
			$form->setButton1("§cDelete");
			$form->setButton2("Cancel");
			$form->sendToPlayer($p);
			$this->forms[$form->getId()] = self::PANEL_DELETE_ARENA;

			return;
		}
		$data = $this->data[$p->getName()];
		unlink($this->plugin->getDataFolder() . "arenas/$data->arenaName.yml");
		$this->plugin->getArenaManager()->deleteArena($data->arenaName);
		$p->sendMessage(str_replace("{ARENA}", $data->arenaName, $this->plugin->getMsg($p, 'arena-delete')));
		$this->cleanupArray($p);
	}

	/**
	 * @param BlockBreakEvent $e
	 * @priority HIGH
	 */
	public function onBlockBreak(BlockBreakEvent $e){
		$p = $e->getPlayer();
		if(isset($this->data[$p->getName()]) && isset($this->setters[strtolower($p->getName())]['type'])){
			if(!is_null($e->getItem()) && $e->getItem()->getId() === Item::BLAZE_ROD){
				if(!isset($this->mode[strtolower($p->getName())])){
					$this->mode[strtolower($p->getName())] = 1;
				}
				$e->setCancelled(true);
				$b = $e->getBlock();
				$arena = new ConfigManager($this->data[$p->getName()]->arenaName, $this->plugin);
				if($this->setters[strtolower($p->getName())]['type'] == "setjoinsign"){
					$arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
					$p->sendMessage($this->plugin->getMsg($p, 'panel-join-sign'));
					unset($this->setters[strtolower($p->getName())]['type']);

					$this->cleanupArray($p);

					return;
				}
				if($this->setters[strtolower($p->getName())]['type'] == "setspecspawn"){
					$arena->setSpecSpawn($b->x, $b->y, $b->z);
					$p->sendMessage($this->plugin->getMsg($p, 'panel-join-spect'));
					$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
					$p->teleport($spawn, 0, 0);
					unset($this->setters[strtolower($p->getName())]['type']);

					$this->cleanupArray($p);

					return;
				}
				if($this->setters[strtolower($p->getName())]['type'] == "spawnpos"){
					if($this->mode[strtolower($p->getName())] >= 1 && $this->mode[strtolower($p->getName())] <= $arena->arena->getNested('arena.max_players')){
						$arena->arena->setNested("arena.spawn_positions.spawn{$this->mode[strtolower($p->getName())]}", [$b->getX(), $b->getY() + 1, $b->getZ()]);
						$p->sendMessage(str_replace("{COUNT}", $this->mode[strtolower($p->getName())], $this->plugin->getMsg($p, 'panel-spawn-pos')));
						$this->mode[strtolower($p->getName())]++;
					}
					if($this->mode[strtolower($p->getName())] === $arena->arena->getNested('arena.max_players') + 1){
						$p->sendMessage($this->plugin->getMsg($p, "panel-spawn-set"));
						$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
						$p->teleport($spawn, 0, 0);
						unset($this->mode[strtolower($p->getName())]);
						unset($this->setters[strtolower($p->getName())]['type']);

						$this->cleanupArray($p);
					}
					$arena->arena->save();

					return;
				}
			}
		}

		if(isset($this->setters[strtolower($p->getName())]['NPC'])
			&& !is_null($e->getItem())
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
				unset($this->setters[strtolower($p->getName())]['NPC']);
				$this->cleanupArray($p);
				NPCValidationTask::setChanged();
			}
			$cfg->save();
		}

		if(isset($this->setters[strtolower($p->getName())]['WORLD'])
			&& !is_null($e->getItem())
			&& $e->getItem()->getId() === Item::BLAZE_ROD){

			unset($this->setters[strtolower($p->getName())]['WORLD']);
		}
	}

	public function showNPCConfiguration(Player $p){
		$p->setGamemode(1);
		$this->setters[strtolower($p->getName())]['NPC'] = "SETUP-NPC";
		$this->mode[strtolower($p->getName())] = 1;
		$p->sendMessage($this->plugin->getMsg($p, 'panel-spawn-wand'));
		$this->setMagicWand($p);
	}

}