<?php

namespace larryTheCoder\task;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;

class RefreshSigns extends PluginTask {
    public $prefix = TextFormat::GRAY . "[" . TextFormat::WHITE . TextFormat::BOLD . "S" . TextFormat::RED . "G" . TextFormat::RESET . TextFormat::GRAY . "] ";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$allplayers = $this->plugin->getServer()->getOnlinePlayers();
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]==$this->prefix)
				{
					$aop = 0;
					foreach($allplayers as $player){if($player->getLevel()->getFolderName()==$text[2]){$aop=$aop+1;}}
					$ingame = TextFormat::AQUA . "[Join]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($text[2] . "PlayTime")!=780)
					{
						$ingame = TextFormat::DARK_PURPLE . "[Running]";
					}
					else if($aop>=24)
					{
						$ingame = TextFormat::GOLD . "[Full]";
					}
					$t->setText($ingame,TextFormat::YELLOW  . $aop . " / 24",$text[2],$this->prefix);
				}
			}
		}
	}
}