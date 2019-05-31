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

namespace larryTheCoder;

use larryTheCoder\commands\SkyWarsCommand;
use larryTheCoder\formAPI\FormAPI;
use larryTheCoder\items\RandomChest;
use larryTheCoder\libs\cages\ArenaCage;
use larryTheCoder\libs\kits\Kits;
use larryTheCoder\npc\FakeHuman;
use larryTheCoder\panel\FormPanel;
use larryTheCoder\provider\{MySqlDatabase, SkyWarsDatabase, SQLite3Database};
use larryTheCoder\task\NPCValidationTask;
use larryTheCoder\task\StartLoadArena;
use larryTheCoder\utils\{Settings, Utils};
use onebone\economyapi\EconomyAPI;
use pocketmine\command\{Command, CommandSender};
use pocketmine\entity\Entity;
use pocketmine\event\{Listener, player\PlayerJoinEvent};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\{Plugin, PluginBase};
use pocketmine\utils\{Config, TextFormat};

/**
 * The main class for SkyWars plugin
 * Was a build for Alair069.
 *
 * @package larryTheCoder
 */
class SkyWarsPE extends PluginBase implements Listener {

	const CONFIG_VERSION = "CrazyDave";
	/** @var SkyWarsPE */
	public static $instance;
	/** @var Config */
	public $msg;
	/** @var SkyWarsCommand */
	public $cmd;
	/** @var Item[] */
	public $inv = [];
	/** @var array */
	public $setters = [];
	/** @var EconomyAPI|Plugin */
	public $economy;
	/** @var FormAPI */
	public $formAPI;
	/** @var FormPanel */
	public $panel;
	/** @var RandomChest */
	public $chest;
	/** @var FakeHuman[] */
	public $entities;
	/** @var array */
	private $translation = [];
	/** @var ArenaManager */
	private $arenaManager;
	/** @var SkyWarsDatabase */
	private $database;
	/** @var bool */
	private $disabled;
	/** @var ArenaCage */
	private $cage = null;
	/** @var Kits */
	private $kits = null;

	public static function getInstance(){
		return self::$instance;
	}

	public function onLoad(){
		self::$instance = $this;

		$this->initConfig();
		$this->initDatabase();
	}

	public function initConfig(){
		Utils::ensureDirectory();
		Utils::ensureDirectory("image/");
		Utils::ensureDirectory("language/");
		Utils::ensureDirectory("arenas/");
		Utils::ensureDirectory("arenas/worlds");
		$this->saveResource("chests.yml");
		$this->saveResource("config.yml");
		$this->saveResource("scoreboard.yml");
		$this->saveResource("image/map.png");
		$this->saveResource("arenas/default.yml");
		$this->saveResource("language/en_US.yml");
		$this->saveResource("language/pt_BR.yml");

		$cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if($cfg->get("config-version") !== SkyWarsPE::CONFIG_VERSION){
			rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.yml.old");
			$this->saveResource("config.yml");
		}
		Settings::init(new Config($this->getDataFolder() . "config.yml", Config::YAML));
		foreach(glob($this->getDataFolder() . "language/*.yml") as $file){
			$locale = new Config($file, Config::YAML);
			$localeCode = basename($file, ".yml");
			if($locale->get("config-version") < 4){
				$this->getServer()->getLogger()->info($this->getPrefix() . "§cLanguage '" . $localeCode . "' is old, using new one");
				$this->saveResource("language/" . $localeCode . ".yml", true);
			}
			$this->translation[strtolower($localeCode)] = $locale;
		}

		if(empty($this->translation)){
			$this->getServer()->getLogger()->error($this->getPrefix() . "§cNo locales been found, this is discouraged.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			$this->disabled = true;
			self::$instance = null;

			return;
		}
		$this->getServer()->getLogger()->info($this->getPrefix() . "§aTracked and flashed §e" . count($this->translation) . "§a locales");
	}

	public function getPrefix(){
		return Settings::$prefix;
	}

	private function initDatabase(){
		switch(strtolower(Settings::$selectedDatabase)){
			case "sqlite":
				$this->database = new SQLite3Database($this);
				break;
			case "mysql":
				$this->database = new MySqlDatabase($this);
				break;
			default:
				$this->getServer()->getLogger()->warning($this->getPrefix() . "§cUnknown database §e" . Settings::$selectedDatabase);
				$this->getServer()->getLogger()->warning($this->getPrefix() . "§aUsing default database: sqlite");
				$this->database = new SQLite3Database($this);
				break;
		}
	}

	public function onEnable(){
		// Should not even run if the plugin is disabled
		if($this->disabled){
			return;
		}
		$this->getServer()->getLogger()->info($this->getPrefix() . "§eStarting SkyWarsForPE modules...");

		$this->checkPlugins();

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->cmd = new SkyWarsCommand($this);
		$this->arenaManager = new ArenaManager($this);
		$this->formAPI = new FormAPI($this);
		$this->panel = new FormPanel($this);
		$this->chest = new RandomChest($this);

		$this->checkLibraries();
		$this->getArenaManager()->checkArenas();
		$this->getScheduler()->scheduleDelayedTask(new StartLoadArena($this), 40);
		$this->checkLobby();
		$this->loadHumans();

		$this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::GREEN . "SkyWarsForPE has been enabled");
	}

	private function loadHumans(){
		$cfg = new Config($this->getDataFolder() . "npc.yml", Config::YAML);

		$npc1E = $cfg->get("npc-1", []);
		$npc2E = $cfg->get("npc-2", []);
		$npc3E = $cfg->get("npc-3", []);

		if(count($npc1E) < 1 || count($npc2E) < 1 || count($npc3E) < 1){
			$this->getServer()->getLogger()->info($this->getPrefix() . "§7No TopWinners spawn location were found.");
			$this->getServer()->getLogger()->info($this->getPrefix() . "§7Please reconfigure TopWinners spawn locations");

			return;
		}

		Utils::loadFirst($npc1E[3]);
		Utils::loadFirst($npc2E[3]);
		Utils::loadFirst($npc3E[3]);

		$level = $this->getServer()->getLevelByName($npc1E[3]);

		$nbt1 = Entity::createBaseNBT(new Vector3($npc1E[0], $npc1E[1], $npc1E[2]));
		$nbt2 = Entity::createBaseNBT(new Vector3($npc2E[0], $npc2E[1], $npc2E[2]));
		$nbt3 = Entity::createBaseNBT(new Vector3($npc3E[0], $npc3E[1], $npc3E[2]));

		$entity1 = new FakeHuman($level, $nbt1, 1);
		$entity2 = new FakeHuman($level, $nbt2, 2);
		$entity3 = new FakeHuman($level, $nbt3, 3);

		$entity1->spawnToAll();
		$entity2->spawnToAll();
		$entity3->spawnToAll();

		$this->entities[] = $entity1;
		$this->entities[] = $entity2;
		$this->entities[] = $entity3;

		// Delay 2 seconds for this task to run and then repeat it
		// every 1 second. <--- Sounds bad but its okay?
		$this->getScheduler()->scheduleDelayedRepeatingTask(new NPCValidationTask($this), 40, 20);
	}

	private function checkPlugins(){
		$ins = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		if($ins instanceof EconomyAPI){
			$this->economy = $ins;
		}
	}

	public function getArenaManager(): ArenaManager{
		return $this->arenaManager;
	}

	private function checkLobby(){
		$lobby = $this->getDatabase()->getLobby();
		if(is_integer($lobby)){
			$this->getDatabase()->setLobby($this->getServer()->getDefaultLevel()->getSpawnLocation());

			return;
		}
		Utils::loadFirst($lobby->getLevel()->getName());
	}

	public function getDatabase(): SkyWarsDatabase{
		return $this->database;
	}

	public function onDisable(){
		Utils::unLoadGame();

		// Cancel all the damn tasks
		$this->getScheduler()->cancelAllTasks();
		$this->database->close();

		$this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::RED . 'SkyWarsForPE has disabled');
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		return $this->cmd->onCommand($sender, $command, $args);
	}

	/**
	 * Get the translation for player and console too
	 *
	 * @param null|CommandSender $p
	 * @param $key
	 * @param bool $prefix
	 * @return string
	 */
	public function getMsg(?CommandSender $p, $key, $prefix = true){
		$msg = "Locale could not found";

		if(!is_null($p) && $p instanceof Player){
			if(isset($this->translation[strtolower($p->getLocale())])){
				$msg = str_replace(["&", "%prefix"], ["§", $this->getPrefix()], $this->translation[strtolower($p->getLocale())]->get($key));
			}elseif(isset($this->translation["en_us"])){
				$msg = str_replace(["&", "%prefix"], ["§", $this->getPrefix()], $this->translation["en_us"]->get($key));
			}else{
				$this->getServer()->getLogger()->error($this->getPrefix() . "ERROR: LOCALE COULD NOT FOUND! LOCALE COULD NOT FOUND!");
			}
		}elseif(isset($this->translation["en_us"])){
			$msg = str_replace(["&", "%prefix"], ["§", $this->getPrefix()], $this->translation["en_us"]->get($key));
		}else{
			$this->getServer()->getLogger()->error($this->getPrefix() . "ERROR: LOCALE COULD NOT FOUND! LOCALE COULD NOT FOUND!");
		}

		return ($prefix ? $this->getPrefix() : "") . $msg;
	}

	/**
	 * @param PlayerJoinEvent $e
	 * @priority MONITOR
	 */
	public function onPlayerLogin(PlayerJoinEvent $e){
		$p = $e->getPlayer();
		# Config configuration
		$result = $this->getDatabase()->createNewData($p->getName());
		if($result !== SkyWarsDatabase::DATA_ALREADY_AVAILABLE){
			if($result === SkyWarsDatabase::DATA_EXECUTE_SUCCESS){
				$this->getServer()->getLogger()->info("§aRegistered §e" . $p->getName() . " §aInto database...");
			}else{
				$this->getServer()->getLogger()->info("§cFailed to register §e" . $p->getName() . " §aInto database...");
			}
		}
	}

	/**
	 * Check private libraries for this plugin.
	 * The features are private. The features are:
	 * - Cages
	 * - Kits
	 * - Diagnostics
	 */
	private function checkLibraries(){
		// No its private, you can't have it, do not open an issue about that.
		$cagesModule = class_exists("\larryTheCoder\libs\cages\ArenaCage");
		$kitsModule = class_exists("\larryTheCoder\libs\kits\Kits");
		if($cagesModule){
			$this->cage = new ArenaCage($this);
		}
		if($kitsModule){
			$this->kits = new Kits($this);
		}
	}

	/**
	 * @return null|ArenaCage
	 */
	public function getCage(): ?ArenaCage{
		return $this->cage;
	}

	/**
	 * @return null|Kits
	 */
	public function getKits(): ?Kits{
		return $this->kits;
	}
}
