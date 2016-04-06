<?php

namespace larryTheCoder\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;

class RefreshSigns extends PluginTask {

    public function __construct($plugin) {
        $this->plugin = $plugin;
        parent::__construct($plugin);
    }
    
    public function onRun($tick) {
        $allplayers = $this->plugin->getServer()->getOnlinePlayers();
        $level = $this->plugin->getServer()->getDefaultLevel();
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Sign) {
                $text = $t->getText();
                if ($text[0] == $this->plugin->getPrefix()) {
                    $aop = 0;
                    foreach ($allplayers as $player) {
                        if ($player->getLevel()->getFolderName() == $text[3]) {
                            $aop = $aop + 1;
                        }
                    }
                    $ingame = "§b§lJoin";
                    $config = new Config($this->plugin->getDataFolder() . "arenas.yml", Config::YAML);
                    if ($config->get($text[3] . "PlayTime") != 780) {
                        $ingame = "§5§lRunning";
                    } else

                    if ($aop >= $this->plugin->config->get("max_players")) {
                        $ingame = "§c§lFull";
                    }
                    $t->setText($this->plugin->getPrefix(), "§c" . $aop . "§f/§c12", $ingame, "§6§l" . $text[3]);
                }
            }
        }
    }

}
