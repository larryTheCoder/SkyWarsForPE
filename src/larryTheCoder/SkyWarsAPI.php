<?php

// LANGUAGE CHECK SUCCESS
/**
 * TO-DO list for 1.9_Alpha
 * <X> Player kill message on Level
 * < > Better MOTD on EntityLevelChange
 * < > Add 1/2 arena loading
 * < > Make first release of SkyWarsForPE
 */

namespace larryTheCoder;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\plugin\Plugin;
use pocketmine\event\Listener;
use larryTheCoder\Arena\Arena;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use larryTheCoder\Utils\SkyWarsListener;
use larryTheCoder\Commands\SkyWarsCommand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;

/**
 * SkyWarsAPI <version 7> : MCPE Minigame
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < BETA | Testing >
 * 
 */
class SkyWarsAPI extends PluginBase implements Listener {

    public $arenas = [];
    public $maps = [];
    public $cfg;
    public $msg;
    public $ins = [];
    public $selectors = [];
    public $inv = [];
    public $setters = [];
    public $economy;
    public $shop = null;
    public $listener = null;
    public $mode = 0;

    public function onEnable() {
        $this->initConfig();
        $this->registerEconomy();
        $this->checkArenas();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!$this->getServer()->isLevelGenerated($this->cfg->getNested('lobby.world'))) {
            $this->getServer()->generateLevel($this->cfg->getNested('lobby.world'));
        }
        $this->loadClasses();
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::GREEN . "SkyWarsForPE has been enabled");
    }

    public function onDisable() {
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::RED . 'SkyWarsForPE has disabled');
    }

    public function loadClasses() {
        $this->listener = SkyWarsListener::getInstance($this);
        $this->cmd = new SkyWarsCommand($this);
        //$this->shop = new SkyWarsShopAPI($this->economy);
    }

    public function initConfig() {
        if (!file_exists($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if (!is_file($this->getDataFolder() . "config.yml")) {
            $this->saveResource("config.yml");
        }
        // TO-DO shop
        $this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if (!file_exists($this->getDataFolder() . "/arenas/worlds/")) {
            @mkdir($this->getDataFolder() . "/arenas/worlds/");
        }
        if (!is_file($this->getDataFolder() . "chests.yml")) {
            $this->saveResource("chests.yml");
        }
        if (!file_exists($this->getDataFolder() . "language/")) {
            @mkdir($this->getDataFolder() . "language/");
        }
        if (!file_exists($this->getDataFolder() . "arenas/")) {
            @mkdir($this->getDataFolder() . "arenas/");
            $this->saveResource("arenas/default.yml");
        }
        if (!is_file($this->getDataFolder() . "language/English.yml")) {
            $this->saveResource("language/English.yml");
        } else {
            $this->msg = new Config($this->getDataFolder() . "language/{$this->cfg->get('language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->cfg->get('language')}");
        }
    }

    public function checkArenas() {
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::AQUA . "checking arena files...");
        foreach (glob($this->getDataFolder() . "arenas/*.yml") as $file) {
            $arena = new Config($file, Config::YAML);
            if (strtolower($arena->get("enabled")) === "false") {
                $this->arenas[basename($file, ".yml")] = $arena->getAll();
                $this->arenas[basename($file, ".yml")]['enable'] = false;
                $fname = basename($file);
                $this->getServer()->getLogger()->info($this->getPrefix() . "§a$fname §7§l-§r§c is disabled");
            } else {
                if ($this->checkFile($arena) === true) {
                    $fname = basename($file);
                    $this->setArenasData($arena, basename($file, ".yml"));
                    $this->getServer()->getLogger()->info($this->getPrefix() . "§c$fname §7§l-§r§a checking sucessful");
                } else {
                    $this->arenas[basename($file, ".yml")] = $arena->getAll();
                    $this->arenas[basename($file, ".yml")]['enable'] = false;
                    //$this->setArenasData($arena, basename($file, ".yml"), false);
                    $fname = basename($file, ".yml");
                    $this->getServer()->getLogger()->error("Arena $fname is not set properly");
                }
            }
        }
    }

    // COPY from svile plugin SkyWars-Pocketmine
    public function getChestContents() {
        $items = array(
            //ARMOR
            'armor' => array(
                array(
                    Item::LEATHER_CAP,
                    Item::LEATHER_TUNIC,
                    Item::LEATHER_PANTS,
                    Item::LEATHER_BOOTS
                ),
                array(
                    Item::GOLD_HELMET,
                    Item::GOLD_CHESTPLATE,
                    Item::GOLD_LEGGINGS,
                    Item::GOLD_BOOTS
                ),
                array(
                    Item::CHAIN_HELMET,
                    Item::CHAIN_CHESTPLATE,
                    Item::CHAIN_LEGGINGS,
                    Item::CHAIN_BOOTS
                ),
                array(
                    Item::IRON_HELMET,
                    Item::IRON_CHESTPLATE,
                    Item::IRON_LEGGINGS,
                    Item::IRON_BOOTS
                ),
                array(
                    Item::DIAMOND_HELMET,
                    Item::DIAMOND_CHESTPLATE,
                    Item::DIAMOND_LEGGINGS,
                    Item::DIAMOND_BOOTS
                )
            ),
            //WEAPONS
            'weapon' => array(
                array(
                    Item::WOODEN_SWORD,
                    Item::WOODEN_AXE,
                ),
                array(
                    Item::GOLD_SWORD,
                    Item::GOLD_AXE
                ),
                array(
                    Item::STONE_SWORD,
                    Item::STONE_AXE
                ),
                array(
                    Item::IRON_SWORD,
                    Item::IRON_AXE
                ),
                array(
                    Item::DIAMOND_SWORD,
                    Item::DIAMOND_AXE
                )
            ),
            //FOOD
            'food' => array(
                array(
                    Item::RAW_PORKCHOP,
                    Item::RAW_CHICKEN,
                    Item::MELON_SLICE,
                    Item::COOKIE
                ),
                array(
                    Item::RAW_BEEF,
                    Item::CARROT
                ),
                array(
                    Item::APPLE,
                    Item::GOLDEN_APPLE
                ),
                array(
                    Item::BEETROOT_SOUP,
                    Item::BREAD,
                    Item::BAKED_POTATO
                ),
                array(
                    Item::MUSHROOM_STEW,
                    Item::COOKED_CHICKEN
                ),
                array(
                    Item::COOKED_PORKCHOP,
                    Item::STEAK,
                    Item::PUMPKIN_PIE
                ),
            ),
            //THROWABLE
            'throwable' => array(
                array(
                    Item::BOW,
                    Item::ARROW
                ),
                array(
                    Item::SNOWBALL
                ),
                array(
                    Item::EGG
                )
            ),
            //BLOCKS
            'block' => array(
                Item::STONE,
                Item::WOODEN_PLANK,
                Item::COBBLESTONE,
                Item::DIRT
            ),
            //OTHER
            'other' => array(
                array(
                    Item::WOODEN_PICKAXE,
                    Item::GOLD_PICKAXE,
                    Item::STONE_PICKAXE,
                    Item::IRON_PICKAXE,
                    Item::DIAMOND_PICKAXE
                ),
                array(
                    Item::STICK,
                    Item::STRING
                )
            )
        );

        $templates = [];
        for ($i = 0; $i < 10; $i++) {

            $armorq = mt_rand(0, 1);
            $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
            $armor1 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            if ($armorq) {
                $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
                $armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            } else {
                $armor2 = array(0, 1);
            }
            unset($armorq, $armortype);

            $weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
            $weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
            unset($weapontype);

            $ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
            $food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
            unset($ftype);

            $add = mt_rand(0, 1);
            if ($add) {
                $tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
                if (count($tr) == 2) {
                    $throwable1 = array($tr[1], mt_rand(10, 20));
                    $throwable2 = array($tr[0], 1);
                } else {
                    $throwable1 = array(0, 1);
                    $throwable2 = array($tr[0], mt_rand(5, 10));
                }
                $other = array(0, 1);
            } else {
                $throwable1 = array(0, 1);
                $throwable2 = array(0, 1);
                $ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
                $other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
            }
            unset($add, $tr, $ot);

            $block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);

            $contents = array(
                $armor1,
                $armor2,
                $weapon,
                $food,
                $throwable1,
                $throwable2,
                $block,
                $other
            );
            shuffle($contents);
            $fcontents = array(
                mt_rand(1, 2) => array_shift($contents),
                mt_rand(3, 5) => array_shift($contents),
                mt_rand(6, 10) => array_shift($contents),
                mt_rand(11, 15) => array_shift($contents),
                mt_rand(16, 17) => array_shift($contents),
                mt_rand(18, 20) => array_shift($contents),
                mt_rand(21, 25) => array_shift($contents),
                mt_rand(26, 27) => array_shift($contents),
            );
            $templates[] = $fcontents;
        }

        shuffle($templates);
        return $templates;
    }

    public function unsetPlayers(Player $p) {
        if (isset($this->selectors[strtolower($p->getName())])) {
            unset($this->selectors[strtolower($p->getName())]);
        }
        if (isset($this->setters[strtolower($p->getName())])) {
            $this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
            if ($this->isArenaSet($this->setters[strtolower($p->getName())]['arena'])) {
                $a = new Arena($this->setters[strtolower($p->getName())]['arena'], $this);
                $a->setup = false;
            }
            unset($this->setters[strtolower($p->getName())]);
        }
    }

    public function getPlayerArena(Player $p) {
        foreach ($this->ins as $arena) {
            $players = array_merge($arena->ingamep, $arena->waitingp, $arena->spec);
            if (isset($players[strtolower($p->getName())])) {
                return $arena;
            }
        }
        return false;
    }

    public function copyr($source, $dest) {
        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            $this->copyr("$source/$entry", "$dest/$entry");
        }

        // Clean up
        $dir->close();
        return true;
    }

    public function isArenaSet($name) {
        if (isset($this->ins[$name])) {
            return true;
        }
        return false;
    }

    public function reloadArena($name) {
        $arena = new Config($this->getDataFolder() . "arenas/$name.yml");
        if (isset($this->ins[$name])) {
            $this->ins[$name]->setup = false;
        }
        if (!$this->checkFile($arena) || $arena->get('enabled') === "false") {
            $this->arenas[$name] = $arena->getAll();
            $this->arenas[$name]['enable'] = 'false';
            return;
        }
        if ($this->arenas[$name]['enable'] === 'false') {
            $this->setArenasData($arena, $name);
            return;
        }
        $this->arenas[$name] = $arena->getAll();
        $this->arenas[$name]['enable'] = 'true';
        $this->ins[$name]->data = $this->arenas[$name];
    }

    public function arenaExist($name) {
        if (isset($this->arenas[$name])) {
            return true;
        }
        return false;
    }

    public function onQuit(PlayerQuitEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }

    public function onKick(PlayerKickEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }

    public function loadInvs() {
        foreach ($this->getServer()->getOnlinePlayers() as $p) {
            if (isset($this->inv[strtolower($p->getName())])) {
                foreach ($this->inv as $slot => $i) {
                    list($id, $dmg, $count) = explode(":", $i);
                    $item = Item::get($id, $dmg, $count);
                    $p->getInventory()->setItem($slot, $item);
                    unset($this->plugin->inv[strtolower($p->getName())]);
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        $this->cmd->onCommand($sender, $command, $label, $args);
    }

    public function setArenasData(Config $arena, $name) {
        $this->arenas[$name] = $arena->getAll();
        $this->arenas[$name]['enable'] = true;
        $game = new Arena($name, $this);
        $game->enableScheduler();
        $this->ins[$name] = $game;
        $this->getServer()->getPluginManager()->registerEvents($game, $this);
    }

    public function checkFile(Config $arena) {
        if (!(is_numeric($arena->getNested("signs.join_sign_x")) && is_numeric($arena->getNested("signs.join_sign_y")) && is_numeric($arena->getNested("signs.join_sign_z")) && is_numeric($arena->getNested("arena.max_game_time")) && is_string($arena->getNested("signs.join_sign_world")) && is_string($arena->getNested("signs.status_line_1")) && is_string($arena->getNested("signs.status_line_2")) && is_string($arena->getNested("signs.status_line_3")) && is_string($arena->getNested("signs.status_line_4")) && is_numeric($arena->getNested("signs.return_sign_x")) && is_numeric($arena->getNested("signs.return_sign_y")) && is_numeric($arena->getNested("signs.return_sign_z")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("chest.refill_rate")) && is_numeric($arena->getNested("arena.spec_spawn_x")) && is_numeric($arena->getNested("arena.spec_spawn_y")) && is_numeric($arena->getNested("arena.spec_spawn_z")) && is_numeric($arena->getNested("arena.max_players")) && is_numeric($arena->getNested("arena.min_players")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.starting_time")) && is_array($arena->getNested("arena.spawn_positions")) && is_string($arena->getNested("arena.finish_msg_levels")) && !is_string($arena->getNested("arena.money_reward")))) {
            return false;
        }
        if (!((strtolower($arena->getNested("signs.enable_status")) == "true" || strtolower($arena->getNested("signs.enable_status")) == "false") && (strtolower($arena->getNested("arena.spectator_mode")) == "true" || strtolower($arena->getNested("arena.spectator_mode")) == "false") && (strtolower($arena->getNested("chest.refill")) == "true" || strtolower($arena->getNested("chest.refill")) == "false") && (strtolower($arena->getNested("arena.time")) == "true" || strtolower($arena->getNested("arena.time")) == "day" || strtolower($arena->getNested("arena.time")) == "night" || is_numeric(strtolower($arena->getNested("arena.time")))) && (strtolower($arena->getNested("arena.start_when_full")) == "true" || strtolower($arena->getNested("arena.start_when_full")) == "false") && (strtolower($arena->get("enabled")) == "true" || strtolower($arena->get("enabled")) == "false"))) {
            return false;
        }
        return true;
    }

    public function onBlockBreak(BlockBreakEvent $e) {
        $p = $e->getPlayer();
        if (isset($this->setters[strtolower($p->getName())]['arena']) && isset($this->setters[strtolower($p->getName())]['type'])) {
            $e->setCancelled(true);
            $b = $e->getBlock();
            $arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
            if ($this->setters[strtolower($p->getName())]['type'] == "setjoinsign") {
                $arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
                $p->sendMessage($this->getPrefix() . $this->getMsg('joinsign'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setspecspawn") {
                $arena->setSpecSpawn($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('spectatorspawn'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "spawnpos") {
                if ($this->mode >= 1 && $this->mode <= $arena->arena->getNested('arena.max_players')) {
                    $arena->arena->setNested("arena.spawn_positions.spawn$this->mode", [$b->getX(), $b->getY() + 2, $b->getZ()]);
                    $p->sendMessage(str_replace("%1", $this->mode, $this->getPrefix() . $this->getMsg('arena_setup_spawnpos')));
                    $this->mode++;
                    if ($this->mode == $arena->arena->getNested('arena.max_players') + 1) {
                        $p->sendMessage($this->getPrefix() . "spawnpos");
                    }
                } else if ($this->mode == $arena->arena->getNested('arena.max_players') + 1) {
                    $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
                    $p->teleport($spawn, 0, 0);
                    $this->mode = 0;
                    unset($this->setters[strtolower($p->getName())]['type']);
                }
                $arena->arena->save();
                return;
            }
        }
    }

    public function onChat(PlayerChatEvent $e) {
        $p = $e->getPlayer();
        $msg = strtolower(trim($e->getMessage()));
        if (isset($this->setters[strtolower($p->getName())]['arena'])) {
            $e->setCancelled(true);
            $arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
            switch ($msg) {
                case 'joinsign':
                    $this->setters[strtolower($p->getName())]['type'] = 'setjoinsign';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_sign'));
                    return;
                case 'spectatorspawn':
                    $this->setters[strtolower($p->getName())]['type'] = 'setspecspawn';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
                    return;
                case 'spawnpos':
                    $this->setters[strtolower($p->getName())]['type'] = 'spawnpos';
                    $p->teleport($this->getServer()->getLevelByName($arena->arena->getNested('arena.arena_world'))->getSafeSpawn(), 0, 0);
                    $this->mode = 1;
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
                    return;
                case 'done':
                    $p->sendMessage($this->getPrefix() . $this->getMsg('disable_setup_mode'));
                    $this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
                    unset($this->setters[strtolower($p->getName())]);
                    return;
            }
            $args = explode(' ', $msg);
            if (count($args) >= 1 && count($args) <= 3) {
                if ($args[0] === 'help') {
                    $help1 = $this->getMsg('help_joinsign')
                            . $this->getMsg('help_spawnpos')
                            . $this->getMsg('help_spectator')
                            . $this->getMsg('help_statusline')
                            . $this->getMsg('help_world')
                            . $this->getMsg('help_signupdatetime');
                    $help2 = $this->getMsg('help_allowspectator')
                            . $this->getMsg('help_maxtime')
                            . $this->getMsg('help_maxplayers')
                            . $this->getMsg('help_minplayers')
                            . $this->getMsg('help_starttime')
                            . $this->getMsg('help_time');
                    $help3 = $this->getMsg('help_enable')
                            . $this->getMsg('help_setmoney');
                    $helparray = [$help1, $help2, $help3];
                    if (isset($args[1])) {
                        if (intval($args[1]) >= 1 && intval($args[1]) <= 3) {
                            $help = "§9--- §6§lSkyWars setup help§l $args[1]/3§9 ---§r§f";
                            $help .= $helparray[intval(intval($args[1]) - 1)];
                            $p->sendMessage($help);
                            return;
                        }
                        $p->sendMessage($this->getPrefix() . "§6use: §ahelp §b[page 1-3]");
                        return;
                    }
                    $p->sendMessage("§9--- §6§lSkyWars setup help§l 1/3§9 ---§r§f" . $help1);
                    return;
                }
            }
            switch (trim(strtolower($args[0]))) {
                
            }
            if (count(explode(' ', $msg)) >= 3 && strpos($msg, 'statusline') !== 0) {
                $p->sendMessage($this->getPrefix() . $this->getMsg('invalid_arguments'));
                return;
            }
            if (substr($msg, 0, 10) === 'statusline') {
                if (!strlen(substr($msg, 13)) >= 1 || !intval(substr($msg, 11, 1)) >= 1 || !intval(substr($msg, 11, 1) <= 4)) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('statusline_help'));
                    return;
                }
                $arena->setStatusLine($args[1], substr($msg, 13));
                $p->sendMessage($this->getPrefix() . $this->getMsg('statusline'));
                return;
                #    
            } elseif (strpos($msg, 'enable') === 0) {
                if (substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
                    $arena->setEnable(substr($msg, 7));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('enable'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('enable_help'));
                return;
            } elseif (strpos($msg, 'setmoney') === 0) {
                if (!is_numeric(substr($msg, 'setmoney'))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('setmoney_help'));
                }
                $arena->setMoney(substr($msg, 15));
            } elseif (strpos($msg, 'signupdatetime') === 0) {
                if (!is_numeric(substr($msg, 15))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime_help'));
                    return;
                }
                $arena->setUpdateTime(substr($msg, 15));
                $p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime'));
            } elseif (strpos($msg, 'setworld') === 0) {
                if (is_string(substr($msg, 6))) {
                    $arena->setArenaWorld(substr($msg, 6));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('world'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('world_help'));
            } elseif (strpos($msg, 'allowspectator') === 0) {
                if (substr($msg, 15) === 'true' || substr($msg, 15) === 'false') {
                    $arena->setSpectator(substr($msg, 15));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('allowspectator'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('allowspectator_help'));
            } elseif (strpos($msg, 'maxtime') === 0) {
                if (!is_numeric(substr($msg, 8))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('maxtime_help'));
                    return;
                }
                $arena->setMaxTime(substr($msg, 8));
                $p->sendMessage($this->getPrefix() . $this->getMsg('maxtime'));
            } elseif (strpos($msg, 'allowstatus') === 0) {
                if (substr($msg, 12) === 'true' || substr($msg, 12) === 'false') {
                    $arena->setStatus(substr($msg, 12));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('allowstatus'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('allowstatus_help'));
            } elseif (strpos($msg, 'maxplayers') === 0) {
                if (!is_numeric(substr($msg, 11))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('maxplayers_help'));
                    return;
                }
                $arena->setMaxPlayers(substr($msg, 11));
                $p->sendMessage($this->getPrefix() . $this->getMsg('maxplayers'));
            } elseif (strpos($msg, 'minplayers') === 0) {
                if (!is_numeric(substr($msg, 11))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('minplayers_help'));
                    return;
                }
                $arena->setMinPlayers(substr($msg, 11));
                $p->sendMessage($this->getPrefix() . $this->getMsg('minplayers'));
            } elseif (strpos($msg, 'starttime') === 0) {
                if (!is_numeric(substr($msg, 10))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('starttime_help'));
                    return;
                }
                $arena->setStartTime(substr($msg, 10));
                $p->sendMessage($this->getPrefix() . $this->getMsg('starttime'));
            } elseif (strpos($msg, 'time') === 0) {
                if (substr($msg, 5) === 'true' || substr($msg, 5) === 'day' || substr($msg, 5) === 'night' || is_numeric(substr($msg, 5))) {
                    $arena->setTime(substr($msg, 5));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('time'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('time_help'));
            } else {
                $p->sendMessage($this->getPrefix() . $this->getMsg('invalid_arguments'));
            }
        }
    }

    public function setLobby(Player $p) {
        $location = $p->getLocation();
        $this->cfg->setNested("lobby", ["spawn_x" => \round($location->getFloorX(), 0), "spawn_y" => \round($location->getFloorY(), 0), "spawn_z" => \round($location->getFloorZ(), 0), "world" => $p->getLevel()->getName()]);
        $this->cfg->save();
        $p->sendMessage($this->getPrefix() . $this->getMsg("set_main_lobby"));
        return true;
    }

    public function getMsg($key) {
        $msg = $this->msg;
        return \str_replace("&", "§", $msg->get($key));
    }

    public function getPrefix() {
        return \str_replace("&", "§", $this->cfg->get('Prefix'));
    }

    public function registerEconomy() {
        $economy = ["EconomyAPI", "PocketMoney", "MassiveEconomy", "GoldStd"];
        foreach ($economy as $plugin) {
            $ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
            if ($ins instanceof Plugin && $ins->isEnabled()) {
                $this->economy = $ins;
                $this->getServer()->getLogger()->info($this->getPrefix() . "§bSelected economy plugin :§c $plugin");
                return;
            }
        }
        $this->economy = null;
    }

}
