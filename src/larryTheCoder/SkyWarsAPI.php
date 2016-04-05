<?php

/** TO-DO list for v1.8
 *  [ ] Add player kill message
 *  [ ] Add join welcome message
 *  [ ] Add arena in yml
 *  [ ] Add kits!
 *  [ ] Fix lobby
 */

namespace larryTheCoder;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use larryTheCoder\Task\GameSender;
use larryTheCoder\Task\RefreshSigns;
use pocketmine\command\CommandSender;
use larryTheCoder\Commands\SkyWarsCommand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class SkyWarsAPI extends PluginBase implements Listener {

    public $currentLevel = "";
    public $arenas = [];
    public $mode = 0;
    public $config; // config.yml
    public $cfg;   //  arena.yml
    public $msg;

    public function onLoad() {
        $this->initConfig();
        //$this->loadArena(); 
        $this->getServer()->getLogger()->info($this->getPrefix() . $this->getMsg('on_load'));
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->swCommand = new SkyWarsCommand($this);
        $config = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
        if ($config->get("arenas") != null) {
            $this->arenas = $config->get("arenas");
        }
        foreach ($this->arenas as $lev) {
            $this->getServer()->loadLevel($lev);
        }
        $items = [[261, 0, 1], [262, 0, 2], [262, 0, 3], [267, 0, 1], [268, 0, 1], [272, 0, 1], [276, 0, 1], [283, 0, 1]];
        if ($config->get("chestitems") === null) {
            $config->set("chestitems", $items);
        }
        $config->save();
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);
        $this->getServer()->getLogger()->info($this->getPrefix() . $this->getMsg('on_enable'));
    }

    public function initConfig() {
        if (!file_exists($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if (!is_file($this->getDataFolder() . "config.yml")) {
            $this->saveResource("config.yml");
        }
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if (!file_exists($this->getDataFolder() . "languages/")) {
            @mkdir($this->getDataFolder() . "languages/");
        }
        if (!is_file($this->getDataFolder() . "languages/English.yml")) {
            $this->saveResource("languages/English.yml");
        }
        if (!is_file($this->getDataFolder() . "languages/{$this->config->get('Language')}.yml")) {
            $this->msg = new Config($this->getDataFolder() . "languages/English.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language English");
        } else {
            $this->msg = new Config($this->getDataFolder() . "languages/{$this->config->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->config->get('Language')}");
        }
    }

    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        $config = new Config($this->getDataFolder() . "rank.yml", Config::YAML);
        $rank = "";
        if ($this->config->get("chat_format") === true) {
            if ($config->get($player->getName()) != null) {
                $rank = $config->get($player->getName());
            }
            $event->setFormat($rank . "§e" . $player->getName() . " §d:§f " . $message);
        }
    }

    public function getMsg($key) {
        $msg = $this->msg;
        return str_replace("&", "§", $msg->get($key));
    }

    public function getPrefix() {
        return str_replace("&", "§", $this->config->get('Prefix'));
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        $this->swCommand->onCommand($sender, $command, $label, $args);
        switch ($command->getName()) {
            case "lobby":
                $cfg = $this->config->getAll();
                if (!$sender->hasPermission('sw.command.lobby')) {
                    $sender->sendMessage($this->getMsg('has_not_permission'));
                    break;
                }
                if (!($sender instanceof Player)) {
                    $sender->sendMessage("Please run this command in-game");

                    return false;
                }
                $sender->teleport(new Position($cfg["lobby"]["spawn_x"], $cfg["lobby"]["spawn_y"], $cfg["lobby"]["spawn_z"], $this->getServer()->getLevelByName($cfg["lobby"]["world"]))); //teleport to the lobby
                $sender->sendMessage($this->getPrefix() . $this->getMsg('back_lobby'));
                break;
        }
    }

    public function onBlockBreak(BlockBreakEvent $e) {
        $p = $e->getPlayer();
        $level = $p->getLevel()->getFolderName();
        if (in_array($level, $this->arenas)) {
            $e->setCancelled(false);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $e) {
        $p = $e->getPlayer();
        $lev = $p->getLevel()->getFolderName();
        if (in_array($lev, $this->arenas)) {
            $e->setCancelled(false);
        }//TO-DO add lobby world place to fals
    }

    public function setLobby(Player $player) {
        $location = $player->getLocation();
        $this->config->setNested("lobby", array("spawn_x" => round($location->getFloorX(), 0), "spawn_y" => round($location->getFloorY(), 0), "spawn_z" => round($location->getFloorZ(), 0), "world" => $player->getLevel()->getName()));
        $this->config->save();
        $player->sendMessage($this->getPrefix() . $this->getMsg("set_main_lobby"));
        return true;
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $tile = $player->getLevel()->getTile($block);

        if ($tile instanceof Sign) {
            if ($this->mode == $this->config->get("maxplayers") + 2) {
                $tile->setText($this->getPrefix(), "§c0§f/§c12", "§b§lJoin", "§6§bl" . $this->currentLevel);
                $this->refreshArenas();
                $this->currentLevel = "";
                $this->mode = 0;
                $player->sendMessage($this->getPrefix() . $this->getMsg("arena_registered"));
            } else {
                $text = $tile->getText();
                if ($text[3] == $this->getPrefix()) {
                    if ($text[0] == "§b§lJoin") {
                        $config = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
                        $level = $this->getServer()->getLevelByName($text[2]);
                        $aop = count($level->getPlayers());
                        $thespawn = $config->get($text[3] . "Spawn" . ($aop + 1));
                        $spawn = new Position($thespawn[0] + 0.5, $thespawn[1], $thespawn[2] + 0.5, $level);
                        $level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
                        $player->teleport($spawn, 0, 0);
                        $player->setNameTag($player->getName());
                        $player->getInventory()->clearAll();
                        $config2 = new Config($this->getDataFolder() . "rank.yml", Config::YAML);
                        $rank = $config2->get($player->getName());
                        if ($rank == "§b[§aVIP§4+§b]") {
                            $player->getInventory()->setContents(array(Item::get(0, 0, 0)));
                            $player->getInventory()->setHelmet(Item::get(Item::CHAIN_HELMET));
                            $player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
                            $player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
                            $player->getInventory()->setBoots(Item::get(Item::CHAIN_BOOTS));
                            $player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
                            $player->getInventory()->setHotbarSlotIndex(0, 0);
                        } else
                        if ($rank == "§b[§aVIP§b]") {
                            $player->getInventory()->setContents(array(Item::get(0, 0, 0)));
                            $player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
                            $player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
                            $player->getInventory()->setLeggings(Item::get(Item::LEATHER_PANTS));
                            $player->getInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
                            $player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
                            $player->getInventory()->setHotbarSlotIndex(0, 0);
                        } else if ($rank == "§b[§4Youç7Tuberçb]") {
                            $player->getInventory()->setContents(array(Item::get(0, 0, 0)));
                            $player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
                            $player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
                            $player->getInventory()->setLeggings(Item::get(Item::GOLD_LEGGINGS));
                            $player->getInventory()->setBoots(Item::get(Item::GOLD_BOOTS));
                            $player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
                            $player->getInventory()->setHotbarSlotIndex(0, 0);
                        } else if ($rank == "çb[çaVIP§b]") {
                            $player->getInventory()->setContents(array(Item::get(0, 0, 0)));
                            $player->getInventory()->setHelmet(Item::get(Item::DIAMOND_HELMET));
                            $player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
                            $player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
                            $player->getInventory()->setBoots(Item::get(Item::DIAMOND_BOOTS));
                            $player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
                            $player->getInventory()->setHotbarSlotIndex(0, 0);
                        }
                    } else {
                        $player->sendMessage($this->getPrefix() . $this->getMsg("arena_started"));
                    }
                }
            }
        } else if ($this->mode >= 1 && $this->mode <= $this->config->get("maxplayers")) {
            $config = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
            $config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(), $block->getY() + 2, $block->getZ()));
            $player->sendMessage(str_replace("%1", $this->mode, $this->getPrefix() . $this->getMsg("registered_spawn")));
            $this->mode++;
            if ($this->mode == $this->config->get("maxplayers") + 1) {
                $player->sendMessage($this->getPrefix() . $this->getMsg("registered_spawn_last"));
            }
            $config->save();
        } else if ($this->mode == $this->config->get("maxplayers") + 1) {
            $config = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
            $level = $this->getServer()->getLevelByName($this->currentLevel);
            $level->setSpawn(new Vector3($block->getX(), $block->getY() + 2, $block->getZ()));
            $config->set("arenas", $this->arenas);
            $player->sendMessage($this->getPrefix() . $this->getMsg("register_spawn_complete"));
            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
            $player->teleport($spawn, 0, 0);
            $config->save();
            $this->mode = $this->config->get("maxplayers") + 2;
        }
    }

    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $level = $player->getLevel()->getFolderName();
        if (in_array($level, $this->arenas)) {
            $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $sofar = $config->get($level . "StartTime");
            if ($sofar > 0) {
                $to = clone $event->getFrom();
                $to->yaw = $event->getTo()->yaw;
                $to->pitch = $event->getTo()->pitch;
                $event->setTo($to);
            }
        }
    }

    /* I dont know if this will works  */

    public function onDeath(PlayerDeathEvent $e) {
        $this->cfg = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
        if ($e->getEntity()->getLevel()->getName() == $this->cfg->get('arena')) { //if in skywars world 
            $victim = $e->getEntity()->getName();
            $this->addDeath($victim);
            $cause = $e->getEntity()->getLastDamageCause();
            if ($cause instanceof EntityDamageByEntityEvent) {
                $killer = $cause->getDamager();
                if ($killer instanceof Player) {
                    $this->addKill($killer->getName()); //use [PREFIX] LarryZ00 was killed by PvPEncore.
                    $e->setDeathMessage($this->getPrefix() . "çc" . $victim . "çb was killed by çc" . $killer->getName());
                }
            }
        }
    }

    /* Defining my function to start the game */

    public function startGame($level) {
        foreach ($this->getServer()->getLevelByName($level)->getPlayers() as $p) { //get every single player in the level
            if ($p->getGameMode() == 0) {
                $x = $p->getX();
                $y = $p->getY(); //get the ground coordinates
                $z = $p->getZ(); //these are needed to break the glass under the player
                $this->getServer()->getLevelByName($level)->setBlock(new Vector3($x, $y, $z), Block::get(0, 0));
                $p->sendMessage("ç9çl+çrç6----------------------ç9çl+çr");
                $p->sendMessage(" çbMatch has started ");
                $p->sendMessage("çbuse /lobby to leave");
                $p->sendMessage("ç9çl+çrç6----------------------ç9çl+çr");
            }
        }
        return true;
    }
    
    public function refreshArenas() {
        $config = new Config($this->getDataFolder() . "arena.yml", Config::YAML);
        $config->set("arenas", $this->arenas);
        foreach ($this->arenas as $arena) {
            $config->set($arena . "PlayTime", 780);
            $config->set($arena . "StartTime", 20);
        }
        $config->save();
    }
}
