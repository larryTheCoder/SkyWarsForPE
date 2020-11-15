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
use larryTheCoder\database\AsyncLibDatabase;
use larryTheCoder\panel\FormManager;
use larryTheCoder\utils\{fireworks\entity\FireworksRocket, npc\FakeHuman, npc\PedestalManager, Settings, Utils};
use larryTheCoder\utils\cage\ArenaCage;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\{Command, CommandSender};
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\{PluginBase};
use pocketmine\utils\{Config, MainLogger, TextFormat};

/**
 * The main class for SkyWarsForPE infrastructure, originally written for Alair069.
 * However due to some futile decision, this project is open sourced again.
 *
 * @package larryTheCoder
 */
class SkyWarsPE extends PluginBase {

	const CONFIG_VERSION = 2;

	/** @var SkyWarsPE|null */
	public static $instance;

	/** @var Config */
	public $msg;

	/** @var SkyWarsCommand */
	public $cmd;
	/** @var EconomyAPI|null */
	public $economy;

	/** @var Config[] */
	private $translation = [];
	/** @var ArenaManager */
	private $arenaManager;
	/** @var AsyncLibDatabase */
	private $database;
	/** @var ArenaCage */
	private $cage;

	/** @var bool */
	public $disabled;
	/** @var FormManager */
	public $panel;
	/** @var PedestalManager */
	public $pedestalManager;

	public static function getInstance(): ?SkyWarsPE{
		return self::$instance;
	}

	public function onLoad(){
		self::$instance = $this;

		$this->initConfig();
	}

	public function initConfig(): void{
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
		$this->saveResource("language/en_US.yml", true);
		$this->saveResource("language/pt_BR.yml", true);

		$cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		if($cfg->get("config-version") !== SkyWarsPE::CONFIG_VERSION){
			rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config.yml.old");
			$this->saveResource("config.yml");
		}
		Settings::init(new Config($this->getDataFolder() . "config.yml", Config::YAML));

		$folder = glob($this->getDataFolder() . "language/*.yml");
		if($folder === false) throw new \RuntimeException("Unexpected error has occurred while indexing arenas files.");

		foreach($folder as $file){
			$locale = new Config($file, Config::YAML);
			$localeCode = basename($file, ".yml");
			if($locale->get("config-version") < 4){
				$this->getServer()->getLogger()->info(Settings::$prefix . "§cLanguage '" . $localeCode . "' is old, using new one");
				$this->saveResource("language/" . $localeCode . ".yml", true);
			}
			$this->translation[strtolower($localeCode)] = $locale;
		}

		if(empty($this->translation)){
			$this->getServer()->getLogger()->error(Settings::$prefix . "§cNo locales been found, this is discouraged.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			$this->disabled = true;
			self::$instance = null;

			return;
		}

		$this->getServer()->getLogger()->info(Settings::$prefix . "§aTracked and flashed §e" . count($this->translation) . "§a locales");
	}

	/** @var bool */
	private $crashed = true;

	public function onEnable(){
		if(\Phar::running(true) === ""){
			if(!class_exists("poggit\libasynql\libasynql")){
				$this->getLogger()->error("libasynql library not found! Please refer to https://github.com/poggit/libasynql and install this first!");
				$this->getServer()->getPluginManager()->disablePlugin($this);

				return;
			}

			$this->getServer()->getLogger()->warning("You are using an experimental version of SkyWarsForPE. This build may seem to work but it will eventually crash your server soon.");
		}
		if($this->disabled) return;

		$this->getServer()->getLogger()->info(Settings::$prefix . "§eStarting SkyWarsForPE modules...");

		$this->checkPlugins();

		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

		$this->database = new AsyncLibDatabase($this, $this->getConfig()->get("database"));
		$this->cmd = new SkyWarsCommand($this);
		$this->arenaManager = new ArenaManager();
		$this->panel = new FormManager($this);
		$this->cage = new ArenaCage($this);

		Entity::registerEntity(FireworksRocket::class, true, ["Firework", "minecraft:firework_rocket"]);
		Entity::registerEntity(FakeHuman::class, true, ["FakeHuman", "skywars:npc"]);

		$this->getArenaManager()->checkArenas();
		$this->loadHumans();

		$this->crashed = false;

		$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::GREEN . "SkyWarsForPE has been enabled");
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

	public function getArenaManager(): ArenaManager{
		return $this->arenaManager;
	}

	public function getDatabase(): AsyncLibDatabase{
		return $this->database;
	}

	public function getCage(): ArenaCage{
		return $this->cage;
	}

	public function onDisable(){
		try{
			if($this->crashed) return;

			Utils::unLoadGame();

			$this->database->close();
			$this->pedestalManager->closeAll();

			$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::RED . 'SkyWarsForPE has disabled');
		}catch(\Throwable $error){
			MainLogger::getLogger()->logException($error);

			$this->getServer()->getLogger()->info(Settings::$prefix . TextFormat::RED . 'Failed to disable plugin accordingly.');
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		return $this->cmd->onCommand($sender, $command, $args);
	}

	/**
	 * Get the translation for player and console too
	 *
	 * @param null|CommandSender $p
	 * @param string $key
	 * @param bool $prefix
	 *
	 * @return string
	 */
	public function getMsg(?CommandSender $p, string $key, $prefix = true){
		$msg = "Locale could not found";

		if(!is_null($p) && $p instanceof Player){
			if(isset($this->translation[strtolower($p->getLocale())])){
				$msg = str_replace(["&", "%prefix"], ["§", Settings::$prefix], $this->translation[strtolower($p->getLocale())]->get($key));
			}elseif(isset($this->translation["en_us"])){
				$msg = str_replace(["&", "%prefix"], ["§", Settings::$prefix], $this->translation["en_us"]->get($key));
			}else{
				$this->getServer()->getLogger()->error(Settings::$prefix . "ERROR: LOCALE COULD NOT FOUND! LOCALE COULD NOT FOUND!");
			}
		}elseif(isset($this->translation["en_us"])){
			$msg = str_replace(["&", "%prefix"], ["§", Settings::$prefix], $this->translation["en_us"]->get($key));
		}else{
			$this->getServer()->getLogger()->error(Settings::$prefix . "ERROR: LOCALE COULD NOT FOUND! LOCALE COULD NOT FOUND!");
		}

		return ($prefix ? Settings::$prefix : "") . $msg;
	}
}
