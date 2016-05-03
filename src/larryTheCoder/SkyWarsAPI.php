<?php

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
use larryTheCoder\Commands\SkyWarsCommand;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

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
    public $mode = 0;

    public function onEnable() {
        $this->initConfig();
        $this->registerEconomy();
        $this->checkArenas();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!$this->getServer()->isLevelGenerated($this->cfg->getNested('lobby.world'))) {
            $this->getServer()->generateLevel($this->cfg->getNested('lobby.world'));
        }
        $this->cmd = new SkyWarsCommand($this);
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::GREEN . "SkyWarsForPE has been enabled");
    }

    public function onDisable() {
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::RED . 'SkyWarsForPE has disabled');
    }

    public function initConfig() {
        if (!file_exists($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if (!is_file($this->getDataFolder() . "config.yml")) {
            $this->saveResource("config.yml");
        }
        $this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if (!file_exists($this->getDataFolder() . "skywars_worlds/")) {
            @mkdir($this->getDataFolder() . "skywars_worlds/");
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
        }
        if (!is_file($this->getDataFolder() . "language/{$this->cfg->get('Language')}.yml")) {
            $this->msg = new Config($this->getDataFolder() . "language/English.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language English");
        } else {
            $this->msg = new Config($this->getDataFolder() . "language/{$this->cfg->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->cfg->get('Language')}");
        }
    }

    public function checkArenas() {
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::AQUA . "checking arena files...");
        foreach (glob($this->getDataFolder() . "arenas/*.yml") as $file) {
            $arena = new Config($file, Config::YAML);
            if (strtolower($arena->get("enabled")) === "false") {
                $this->arenas[basename($file, ".yml")] = $arena->getAll();
                $this->arenas[basename($file, ".yml")]['enable'] = false;
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

    public function onQuit(PlayerQuitEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }

    public function onKick(PlayerKickEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
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

    /**
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
        $command = strtolower(substr($event->getMessage(), 0, 9));
        if ($command === "/save-all") {
            $this->onCommandProcess($event->getPlayer());
        }
    }

    /**
     * @param ServerCommandEvent $event
     */
    public function onServerCommandProcess(ServerCommandEvent $event) {
        $cmd = strtolower(substr($event->getCommand(), 0, 8));
        if ($cmd === "save-all") {
            $this->onCommandProcess($event->getSender());
        }
    }

    public function onCommandProcess(CommandSender $sender) {
        $cmd = $this->getServer()->getCommandMap()->getCommand("save-all");
        if ($cmd instanceof Command) {
            if ($cmd->testPermissionSilent($sender)) {
                $this->saveConfig();
                $sender->sendMessage($this->getPrefix() . TextFormat::AQUA . "Saved Arenas data");
            }
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
        if (isset($this->ins[$name]))
            return true;

        return false;
    }

    public function reloadArena($name) {
        $arena = new Config($this->getDataFolder() . "arenas/$name.yml");
        if (isset($this->ins[$name]))
            $this->ins[$name]->setup = false;
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
        if (!(is_numeric($arena->getNested("signs.join_sign_x")) && is_numeric($arena->getNested("signs.join_sign_y")) && is_numeric($arena->getNested("signs.join_sign_z")) && is_string($arena->getNested("signs.join_sign_world")) && is_string($arena->getNested("signs.status_line_1")) && is_string($arena->getNested("signs.status_line_2")) && is_string($arena->getNested("signs.status_line_3")) && is_string($arena->getNested("signs.status_line_4")) && is_numeric($arena->getNested("signs.return_sign_x")) && is_numeric($arena->getNested("signs.return_sign_y")) && is_numeric($arena->getNested("signs.return_sign_z")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.spec_spawn_x")) && is_numeric($arena->getNested("arena.spec_spawn_y")) && is_numeric($arena->getNested("arena.spec_spawn_z")) && is_numeric($arena->getNested("arena.max_players")) && is_numeric($arena->getNested("arena.min_players")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.starting_time")) && is_array($arena->getNested("arena.spawn_positions")) && is_string($arena->getNested("arena.finish_msg_levels")) && !is_string($arena->getNested("arena.money_reward")))) {
            return false;
        }
        if (!((strtolower($arena->getNested("signs.enable_status")) == "true" || strtolower($arena->getNested("signs.enable_status")) == "false") && (strtolower($arena->getNested("arena.spectator_mode")) == "true" || strtolower($arena->getNested("arena.spectator_mode")) == "false") && (strtolower($arena->getNested("arena.time")) == "true" || strtolower($arena->getNested("arena.time")) == "day" || strtolower($arena->getNested("arena.time")) == "night" || is_numeric(strtolower($arena->getNested("arena.time")))) && (strtolower($arena->get("enabled")) == "true" || strtolower($arena->get("enabled")) == "false"))) {
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
            if ($this->setters[strtolower($p->getName())]['type'] == "setreturnsign") {
                $arena->setReturnSign($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('returnsign'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setjoinpos") {
                $arena->setJoinPos($b->x, $b->y, $b->z);
                $arena->setArenaWorld($b->level->getName());
                $p->sendMessage($this->getPrefix() . $this->getMsg('startpos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setlobbypos") {
                $arena->setLobbyPos($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('lobbypos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setfirstcorner") {
                $arena->setFirstCorner($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('first_corner'));
                $this->setters[strtolower($p->getName())]['type'] = "setsecondcorner";
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setsecondcorner") {
                $arena->setSecondCorner($b->x, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('second_corner'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "setspecspawn") {
                $arena->setSpecSpawn($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix() . $this->getMsg('spectatorspawn'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "spawnpos") { # HERE
                if ($this->mode >= 1 && $this->mode <= $arena->arena->getNested('arena.max_players')) {
                    $arena->arena->setNested("arena.spawn_positions.spawn$this->mode", [$b->getX(), $b->getY() + 2, $b->getZ()]);
                    $p->sendMessage(str_replace("%1", $this->mode, $this->getPrefix() . $this->getMsg('arena_setup_spawnpos')));
                    $this->mode++;
                    if ($this->mode == $arena->arena->getNested('arena.max_players') + 1) {
                        $p->sendMessage($this->getPrefix() . "Spawn location has been set..teleporting to main world");
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
            if ($this->setters[strtolower($p->getName())]['type'] == "setleavepos") {
                $arena->setLeavePos($b->x, $b->y, $b->z, $b->level->getName());
                $p->sendMessage($this->getPrefix() . $this->getMsg('leavepos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if ($this->setters[strtolower($p->getName())]['type'] == "mainlobby") {
                $this->cfg->setNested("lobby.x", $b->x);
                $this->cfg->setNested("lobby.y", $b->y);
                $this->cfg->setNested("lobby.z", $b->z);
                $this->cfg->setNested("lobby.world", $b->level->getName());
                $p->sendMessage($this->getPrefix() . $this->getMsg('mainlobby'));
                unset($this->setters[strtolower($p->getName())]['type']);
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
                case 'returnsign':
                    $this->setters[strtolower($p->getName())]['type'] = 'setreturnsign';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_sign'));
                    return;
                case 'startpos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setjoinpos';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
                    return;
                case 'lobbypos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setlobbypos';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
                    return;
                case 'corners':
                    $this->setters[strtolower($p->getName())]['type'] = 'setfirstcorner';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
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
                case 'leavepos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setleavepos';
                    $p->sendMessage($this->getPrefix() . $this->getMsg('break_block'));
                    return;
                case 'done':
                    $p->sendMessage($this->getPrefix() . $this->getMsg('disable_setup_mode'));
                    $this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
                    unset($this->setters[strtolower($p->getName())]);
                    return;
            }
            $args = explode(' ', $msg);
            if (count($args) >= 1 && count($args) <= 2) {
                if ($args[0] === 'help') {
                    $help1 = $this->getMsg('help_joinsign')
                            . $this->getMsg('help_returnsign')
                            . $this->getMsg('help_startpos')
                            . $this->getMsg('help_lobbypos')
                            . $this->getMsg('help_corners')
                            . $this->getMsg('help_spectatorspawn')
                            . $this->getMsg('help_leavepos');
                    $help2 = $this->getMsg('help_time')
                            . $this->getMsg('help_colortime')
                            . $this->getMsg('help_type')
                            . $this->getMsg('help_material')
                            . $this->getMsg('help_allowstatus')
                            . $this->getMsg('help_world')
                            . $this->getMsg('help_statusline');
                    $help3 = $this->getMsg('help_allowspectator')
                            . $this->getMsg('help_signupdatetime')
                            . $this->getMsg('help_maxtime')
                            . $this->getMsg('help_maxplayers')
                            . $this->getMsg('help_minplayers')
                            . $this->getMsg('help_spawnpos');
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
            } elseif (strpos($msg, 'enable') === 0) {
                if (substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
                    $arena->setEnable(substr($msg, 7));
                    $p->sendMessage($this->getPrefix() . $this->getMsg('enable'));
                    return;
                }
                $p->sendMessage($this->getPrefix() . $this->getMsg('enable_help'));
                return;
            } elseif (strpos($msg, 'signupdatetime') === 0) {
                if (!is_numeric(substr($msg, 15))) {
                    $p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime'));
                    return;
                }
                $arena->setUpdateTime(substr($msg, 15));
                $p->sendMessage($this->getPrefix() . $this->getMsg('signupdatetime'));
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

    public function setLobby($p) {
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
        $economy = ["EconomyAPI", "AocketMoney", "MassiveEconomy", "GoldStd"];
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
