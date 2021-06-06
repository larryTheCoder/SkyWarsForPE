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

use larryTheCoder\arena\api\translation\TranslationContainer;
use larryTheCoder\commands\SkyWarsCommand;
use larryTheCoder\database\SkyWarsDatabase;
use larryTheCoder\panel\FormManager;
use larryTheCoder\utils\{fireworks\entity\FireworksRocket,
	KitManager,
	LootGenerator,
	npc\FakeHuman,
	npc\PedestalManager,
	permission\PluginPermission,
	Settings,
	Utils
};
use larryTheCoder\utils\cage\CageManager;
use larryTheCoder\worker\LevelAsyncPool;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\{Command, CommandSender};
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\plugin\{PluginBase};
use pocketmine\Server;
use pocketmine\utils\{Config, MainLogger, TextFormat};

/**
 * The main class for SkyWarsForPE infrastructure, originally written for Alair069.
 * However due to some futile decision, this project is open sourced again.
 *
 * @package larryTheCoder
 */
class SkyWarsPE extends PluginBase {

	private const CONFIG_VERSION = 5;
	private const LOCALE_VERSION = 11;
	private const CAGES_VERSION = 2;

	/** @var SkyWarsPE|null */
	private static $instance;

	/** @var SkyWarsCommand */
	private $command;
	/** @var bool */
	private $crashed = true;

	/** @var EconomyAPI|null */
	private $economy;
	/** @var ArenaManager */
	private $arenaManager;
	/** @var FormManager */
	private $panel;
	/** @var PedestalManager|null */
	private $pedestalManager = null;
	/** @var KitManager|null */
	private $kitManager = null;

	public function onLoad(): void{
		self::$instance = $this;

		$this->initConfig();
	}

	public function initConfig(): void{
		Utils::ensureDirectory();
		Utils::ensureDirectory("image/");
		Utils::ensureDirectory("language/");
		Utils::ensureDirectory("scoreboards/");
		Utils::ensureDirectory("arenas/");
		Utils::ensureDirectory("arenas/worlds");
		$this->saveResource("config.yml");
		$this->saveResource("cages.yml");
		$this->saveResource("arenas/default.yml");
		$this->saveResource("looting-tables.json");
		$this->saveResource("language/en_US.yml");
		$this->saveResource("language/es_ES.yml");
		$this->saveResource("language/ko_KR.yml");
		$this->saveResource("language/ru_RU.yml");
		$this->saveResource("language/pt_BR.yml");

		// Load config file first.
		$cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if($cfg->get("config-version") !== SkyWarsPE::CONFIG_VERSION){
			Server::getInstance()->getLogger()->info(Settings::$prefix . TextFormat::YELLOW . "Your config is outdated. saving a newer config file.");

			Utils::oldRenameRecursive();
			$this->saveResource("config.yml");

			$cfg->reload();
		}
		Settings::init(new Config($this->getDataFolder() . "config.yml", Config::YAML));

		// Then load cages file.
		$cfg = new Config($this->getDataFolder() . "cages.yml", Config::YAML);
		if($cfg->get('version-id') < SkyWarsPE::CAGES_VERSION){
			Server::getInstance()->getLogger()->info(Settings::$prefix . TextFormat::YELLOW . "Your cages config is outdated. saving a newer cages config.");

			Utils::oldRenameRecursive("cages.yml");
			$this->saveResource("cages.yml");

			$cfg->reload();
		}
		CageManager::init($cfg);

		foreach(glob($this->getDataFolder() . "language/*.yml") as $file){
			$locale = new Config($file, Config::YAML);
			$localeCode = basename($file, ".yml");

			if($locale->get("config-version") < self::LOCALE_VERSION && file_exists($this->getFile() . "resources/language/" . $localeCode . ".yml")){
				$newLocale = new Config($this->getFile() . "resources/language/" . $localeCode . ".yml");
				if($newLocale->get("config-version") < self::LOCALE_VERSION){
					goto register;
				}

				$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::YELLOW . "§cLanguage '" . $localeCode . "' is outdated, saving a newer locale config.");

				Utils::oldRenameRecursive("language/" . $localeCode . ".yml");
				$this->saveResource("language/" . $localeCode . ".yml", true);

				$locale = $newLocale;
			}

			register:
			TranslationContainer::getInstance()->addTranslation($localeCode, $locale);
		}
	}

	public function onEnable(): void{
		new LevelAsyncPool($this, 4);

		$server = $this->getServer();
		if(\Phar::running(true) === ""){
			if(!class_exists("poggit\libasynql\libasynql")){
				$this->getLogger()->error("libasynql library not found! Please refer to https://github.com/poggit/libasynql and install this first!");
				$server->getPluginManager()->disablePlugin($this);

				return;
			}

			$server->getLogger()->warning("You are using an experimental version of SkyWarsForPE. This build may seem to work but it will eventually crash your server soon.");
		}
		$server->getLogger()->info(Settings::$prefix . "§eStarting SkyWarsForPE modules...");

		$this->checkPlugins();

		$server->getPluginManager()->registerEvents(new EventListener($this), $this);
		$server->getPluginManager()->registerEvents(PluginPermission::getInstance(), $this);

		SkyWarsDatabase::getInstance()->createContext($this->getConfig()->get("database"));
		SkyWarsDatabase::loadLobby();

		LootGenerator::init();

		$this->command = new SkyWarsCommand($this);
		$this->arenaManager = new ArenaManager();
		$this->panel = new FormManager($this);

		if($server->getPluginManager()->getPlugin("EasyKits") !== null){
			$this->kitManager = new KitManager();

			$server->getLogger()->info(Settings::$prefix . TextFormat::GREEN . "EasyKits plugin found! The plugin will use start using it now.");
		}

		$this->getArenaManager()->checkArenas();
		$this->loadHumans();

		$this->crashed = false;

		$server->getLogger()->info(Settings::$prefix . TextFormat::GREEN . "SkyWarsForPE has been enabled");
	}

	private function loadHumans(): void{
		$cfg = new Config($this->getDataFolder() . "npc.yml", Config::YAML);

		$npc1E = $cfg->get("npc-1", []);
		$npc2E = $cfg->get("npc-2", []);
		$npc3E = $cfg->get("npc-3", []);

		if(count($npc1E) < 1 || count($npc2E) < 1 || count($npc3E) < 1){
			$this->getServer()->getLogger()->info(Settings::$prefix . "§7No TopWinners spawn location were found.");
			$this->getServer()->getLogger()->info(Settings::$prefix . "§7Please reconfigure TopWinners spawn locations");

			return;
		}
		$levelName = $npc1E[3];

		Utils::loadFirst($levelName);

		$level = $this->getServer()->getLevelByName($levelName);

		$vectors[] = new Vector3((float)$npc1E[0], (float)$npc1E[1], (float)$npc1E[2]);
		$vectors[] = new Vector3((float)$npc2E[0], (float)$npc2E[1], (float)$npc2E[2]);
		$vectors[] = new Vector3((float)$npc3E[0], (float)$npc3E[1], (float)$npc3E[2]);

		// Force to load the chunks (An error can occur if the world is freshly loaded) aka dummy server owner.
		foreach($vectors as $vector){
			$level->loadChunk($vector->getFloorX() >> 4, $vector->getFloorZ() >> 4);
		}

		$this->pedestalManager = new PedestalManager($vectors, $level);
	}

	private function checkPlugins(): void{
		$ins = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		if($ins instanceof EconomyAPI){
			$this->economy = $ins;
		}else{
			$this->economy = null;
		}
	}

	public static function getInstance(): ?SkyWarsPE{
		return self::$instance;
	}

	public function getArenaManager(): ArenaManager{
		return $this->arenaManager;
	}

	public function getPanel(): FormManager{
		return $this->panel;
	}

	public function getPedestals(): ?PedestalManager{
		return $this->pedestalManager;
	}

	public function getKitManager(): ?KitManager{
		return $this->kitManager;
	}

	/**
	 * @return EconomyAPI|null
	 */
	public function getEconomy(){
		return $this->economy;
	}

	public function onDisable(): void{
		try{
			if($this->crashed) return;

			Utils::unLoadGame();
			SkyWarsDatabase::shutdown();

			LevelAsyncPool::getAsyncPool()->shutdown();

			if($this->pedestalManager !== null){
				$this->pedestalManager->closeAll();
			}

			$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::RED . 'SkyWarsForPE has disabled');
		}catch(\Throwable $error){
			MainLogger::getLogger()->logException($error);

			$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::RED . 'Failed to disable plugin accordingly.');
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		return $this->command->onCommand($sender, $command, $args);
	}
}
