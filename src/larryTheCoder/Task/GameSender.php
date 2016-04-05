<?php

namespace larryTheCoder\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
use larryTheCoder\SkyWarsAPI;

class GameSender extends PluginTask {

    public function __construct(SkyWarsAPI $plugin) {
        $this->plugin = $plugin;
        parent::__construct($plugin);
    }

    public function onRun($tick) {
        $config = new Config($this->plugin->getDataFolder() . "arena.yml", Config::YAML);
        $arenas = $config->get("arena");
        if (!empty($arenas)) {
            foreach ($arenas as $arena) {
                $time = $config->get($arena . "PlayTime");
                $timeToStart = $config->get($arena . "StartTime");
                $levelArena = $this->plugin->getServer()->getLevelByName($arena);
                if ($levelArena instanceof Level) {
                    $playersArena = $levelArena->getPlayers();
                    if (count($playersArena) == 0) {
                        $config->set($arena . "PlayTime", 780);
                        $config->set($arena . "StartTime", 20);
                    } else {

                        if (count($playersArena) >= $this->config->get("minplayers")) {

                            if ($timeToStart > 0) {
                                $timeToStart--;
                                foreach ($playersArena as $pl) {
                                    $pl->sendPopup(str_replace("%1", $timeToStart, $this->plugin->getMsg("arena-onStart")));
                                }
                                if ($timeToStart <= 0) {
                                    $this->refillChests($levelArena);
                                    $this->plugin->startGame($levelArena);
                                }
                                $config->set($arena . "StartTime", $timeToStart);
                            } else {

                                $aop = count($levelArena->getPlayers());

                                if ($aop == 1) {
                                    foreach ($playersArena as $pl) {
                                        $pl->giveReward($pl);
                                        $pl->sendMessage($this->plugin->getMsg("win"));
                                        $pl->getInventory()->clearAll();
                                        $pl->removeAllEffects();
                                        $pl->setNameTag($pl->getName());
                                        $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                        $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                                        $pl->teleport($spawn, 0, 0);
                                        $this->getServer()->unloadLevel($this->getServer()->getLevelByName("SkyWars"));
                                        $this->getServer()->loadLevel($this->get("SkyWars"));
                                    }
                                    $config->set($arena . "PlayTime", 780);
                                    $config->set($arena . "StartTime", 20);
                                }

                                $time--;
                                if ($time >= 180) {
                                    $time2 = $time - 180;
                                    $minutes = $time2 / 20;
                                    if (is_int($minutes) && $minutes > 0) {
                                        foreach ($playersArena as $pl) {
                                            $pl->sendPopup(str_replace("%1", $minutes, $this->plugin->getMsg("arena-popup-ends")));
                                            $this->plugin->giveEffect(1, $pl);
                                        }
                                    } elseif ($time2 == 300) {
                                        foreach ($playersArena as $pl) {
                                            $pl->sendPopup($this->plugin->getPrefix() . $this->plugin->getMsg("arena-message-kill"));
                                        }
                                        $this->refillChests($levelArena);
                                    } elseif ($time2 == 30 || $time2 == 15 || $time2 == 10 || $time2 == 5 || $time2 == 4 || $time2 == 3 || $time2 == 2 || $time2 == 1) {
                                        foreach ($playersArena as $pl) {
                                            $pl->sendMessage(str_replace("%1", $time2, $this->plugin->getPrefix() . $this->plugin->getMsg("arena-message-end")));
                                        }
                                    }
                                    if ($time2 <= 0) {
                                        $spawn = $levelArena->getSafeSpawn();
                                        $levelArena->loadChunk($spawn->getX(), $spawn->getZ());
                                        foreach ($playersArena as $pl) {
                                            $pl->teleport($spawn, 0, 0);
                                        }
                                    }
                                } else {

                                    $minutes = $time / 20;
                                    if (is_int($minutes) && $minutes > 0) {
                                        foreach ($playersArena as $pl) {
                                            $pl->sendMessage(str_replace('%1', $minutes, $this->plugin->getMsg("message_arena_munites")));
                                        }
                                    } elseif ($time == 30 || $time == 15 || $time == 10 || $time == 5 || $time == 4 || $time == 3 || $time == 2 || $time == 1) {
                                        foreach ($playersArena as $pl) {
                                            $pl->sendMessage(str_replace('%1', $time, $this->plugin->getMsg('message_arena_seconds'))); //$time . " seconds remaining"
                                        }
                                    }
                                    if ($time <= 0) {
                                        $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                        $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                                        foreach ($playersArena as $pl) {
                                            $pl->teleport($spawn, 0, 0);
                                            $pl->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg("no_winner"));
                                            $pl->getInventory()->clearAll();
                                        }
                                        $time = 780;
                                    }
                                }
                                $config->set($arena . "PlayTime", $time);
                            }
                        } else {
                            if ($timeToStart <= 0) {
                                foreach ($playersArena as $pl) {
                                    $pl->getInventory()->clearAll();
                                    $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                    $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                                    $pl->teleport($spawn);
                                }
                                $config->set($arena . "PlayTime", 780);
                                $config->set($arena . "StartTime", 20);
                            } else {
                                foreach ($playersArena as $pl) {
                                    $pl->sendPopup($this->plugin->getPrefix() . $this->plugin->getMsg("waiting-game"));
                                }
                                $config->set($arena . "PlayTime", 780);
                                $config->set($arena . "StartTime", 20);
                            }
                        }
                    }
                }
            }
        }
        $config->save();
    }

    public function refillChests(Level $level) {
        $config = new Config($this->plugin->getDataFolder() . "arena.yml", Config::YAML);
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Chest) {
                $chest = $t;
                $chest->getInventory()->clearAll();
                if ($chest->getInventory() instanceof ChestInventory) {
                    for ($i = 0; $i <= 26; $i++) {
                        $rand = rand(1, 3);
                        if ($rand == 1) {
                            $k = array_rand($config->get("chestitems"));
                            $v = $config->get("chestitems")[$k];
                            $chest->getInventory()->setItem($i, Item::get($v[0], $v[1], $v[2]));
                        }
                    }
                }
            }
        }
    }
    
    public function giveReward(Player $p){
        $cfg = new Config($this->plugin->getDataFolder() . "arena.yml", Config::YAML);
        if(isset($cfg['item_reward']) && $cfg['item_reward'] !== null && intval($cfg['item_reward']) !== 0){
            foreach(explode(',', str_replace(' ', '', $cfg['item_reward'])) as $item){
                $exp = explode(':', $item);
                if(isset($exp[0]) && isset($exp[0]) && isset($exp[0])){
                    list($id, $damage, $count) = $exp;
                    if(Item::get($id, $damage, $count) instanceof Item){
                        $p->getInventory()->addItem(Item::get($id, $damage, $count));
                    }
                }
            }
        }
        if(isset($cfg['money_reward'])){
        if($cfg['money_reward'] !== null && $this->plugin->economy !== null){
            $money = $cfg['money_reward'];
            $ec = $this->plugin->economy;
            switch($ec->getName()){
                case "EconomyAPI":
                    $ec->addMoney($p->getName(), $money);
                    break;
                case "PocketMoney":
                    $ec->setMoney($p->getName(), $ec->getMoney($p->getName()));
                    break;
                case "MassiveEconomy":
                    $ec->setMoney($p->getName(), $ec->getMoney($p->getName()));
                    break;
                case "GoldStd":
                    $ec->giveMoney($p, $money);
                    break;
            }
            $p->sendMessage($this->plugin->getPrefix().str_replace('%1', $money, $this->plugin->getMsg('get_money')));
        }
        }
    }
}
