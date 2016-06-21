<?php

namespace larryTheCoder\Commands;

use larryTheCoder\SkyWarsAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\utils\Config;

/**
 * SkyWarsCommand : MCPE Minigame
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < BETA | Testing >
 * 
 */
class SkyWarsCommand {

    private $plugin;

    public function __construct(SkyWarsAPI $e) {
        $this->plugin = $e;
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
        switch ($cmd->getName()) {
            case "lobby":
                if (!$sender->hasPermission('sw.command.lobby')) {
                    $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                    break;
                }
                if (!$sender instanceof Player) {
                    $this->consoleSender($sender);
                    break;
                }
                if (isset($args[1])) {
                    $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('lobby_help'));
                    break;
                }
                if ($this->plugin->getPlayerArena($sender) === false) {
                    $sender->sendMessage($this->plugin->getPrefix() . 'Please use this command in-arena');
                    break;
                }
                $this->plugin->getPlayerArena($sender)->leaveArena($sender);
                break;
        }
        if (strtolower($cmd->getName()) == "sw") {
            if (isset($args[0])) {
                switch (strtolower($args[0])) {
                    case "help":
                        if (!$sender->hasPermission("sw.command.help")) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        $msg = "Â§9--- Â§cÂ§lSkyWars helpÂ§lÂ§9 ---Â§rÂ§f";
                        if ($sender->hasPermission("sw.command.lobby")) {
                            $msg .= $this->plugin->getMsg('lobby');
                        }
                        if ($sender->hasPermission('sw.command.join')) {
                            $msg .= $this->plugin->getMsg('onjoin');
                        }
                        if ($sender->hasPermission('sw.command.start')) {
                            $msg .= $this->plugin->getMsg('start');
                        }
                        if ($sender->hasPermission('sw.command.stop')) {
                            $msg .= $this->plugin->getMsg('stop');
                        }
                        if ($sender->hasPermission('sw.command.kick')) {
                            $msg .= $this->plugin->getMsg('kick');
                        }
                        if ($sender->hasPermission('sw.command.set')) {
                            $msg .= $this->plugin->getMsg('set');
                        }
                        if ($sender->hasPermission('sw.command.delete')) {
                            $msg .= $this->plugin->getMsg('delete');
                        }
                        if ($sender->hasPermission('sw.command.create')) {
                            $msg .= $this->plugin->getMsg('create');
                        }
                        if ($sender->hasPermission('sw.command.reload')) {
                            $msg.= $this->plugin->getMsg('reload');
                        }
                        if ($sender->hasPermission('sw.command.setlobby')) {
                            $msg.= $this->plugin->getMsg('setlobby');
                        }
                        $sender->sendMessage($msg);
                        break;
                    case "reload":
                        if (!$sender->hasPermission("sw.command.reload")) {
                            $sender->sendMessage($this->plugin->getMsg("has_not_permission"));
                        }
                        $plugin = $this->plugin->getServer()->getPluginManager()->getPlugin("SkyWarsForPE");
                        $this->plugin->getServer()->getPluginManager()->disablePlugin($plugin);
                        $this->plugin->getServer()->getPluginManager()->enablePlugin($plugin);
                        $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('reloaded'));
                        return true;
                    case "create":
                        if (!$sender->hasPermission('sw.command.create')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!isset($args[1]) || isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('create_help'));
                            break;
                        }
                        if ($this->plugin->arenaExist($args[1])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_already_exist'));
                            break;
                        }
                        $a = new Config($this->plugin->getDataFolder() . "arenas/$args[1].yml", Config::YAML);
                        file_put_contents($this->plugin->getDataFolder() . "arenas/$args[1].yml", $this->plugin->getResource('arenas/default.yml'));
                        $this->plugin->arenas[$args[1]] = $a->getAll();
                        $a->setNested('arena.arena_world', $args[1]);
                        $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_create'));
                        break;
                    // Functioning
                    case "start":
                        if (!$sender->hasPermission('sw.command.start')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('start_help'));
                            break;
                        }
                        if (isset($args[1])) {
                            if (!isset($this->plugin->ins[$args[1]])) {
                                $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                                break;
                            }
                            if (!count($this->plugin->ins[$args[1]]->waitingp) <= $this->plugin->ins[$args[1]]->getMinPlayers()) {
                                $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('no_players'));
                                break;
                            }
                            $this->plugin->ins[$args[1]]->forcestart = true;
                            $sender->sendMessage(str_replace('%1', $args[1], $this->plugin->getPrefix() . $this->plugin->getMsg('arena_started')));
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('start_help'));
                            break;
                        }
                        if ($this->plugin->getPlayerArena($sender) === false) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('start_help'));
                            break;
                        }
                        if (count($this->plugin->getPlayerArena($sender)->waitingp) >= $this->plugin->getPlayerArena($sender)->getMinPlayers()) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('no_players'));
                            break;
                        }
                        $this->plugin->getPlayerArena($sender)->forcestart === true;
                        $sender->sendMessage(str_replace('%1', $args[1], $this->plugin->getPrefix() . $this->plugin->getMsg('arena_started')));
                        break;
                    case "stop":
                        if (!$sender->hasPermission('sw.command.stop')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('stop_help'));
                            break;
                        }
                        if (isset($args[1])) {
                            if (!isset($this->plugin->ins[$args[1]])) {
                                $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                                break;
                            }
                            $this->plugin->ins[$args[1]]->stopGame();
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('stop_help'));
                            break;
                        }
                        if ($this->plugin->getPlayerArena($sender) === false) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('stop_help'));
                            break;
                        }
                        $this->plugin->getPlayerArena($sender)->stopGame();
                        break;
                    case "delete":
                        if (!$sender->hasPermission('sw.command.delete')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!isset($args[1]) || isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('delete_help'));
                            break;
                        }
                        if (!$this->plugin->arenaExist($args[1])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                            break;
                        }
                        unlink($this->plugin->getDataFolder() . "arenas/$args[1].yml");
                        unset($this->plugin->arenas[$args[1]]);
                        $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_delete'));
                        break;
                    case "kick": // sw kick [arena] [player] [reason]
                        if (!$sender->hasPermission('sw.command.kick')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!isset($args[2]) || isset($args[4])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('kick_help'));
                            break;
                        }
                        if (!isset(array_merge($this->plugin->ins[$args[1]]->ingamep, $this->plugin->ins[$args[1]]->waitingp, $this->plugin->ins[$args[1]]->spec)[strtolower($args[2])])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('player_not_exist'));
                            break;
                        }
                        if (!isset($args[3])) {
                            $args[3] = "";
                        }
                        $this->plugin->ins[$args[1]]->kickPlayer($args[2], $args[3]);
                        break;
                    case "join":
                        if (!$sender->hasPermission('sw.command.join')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $this->consoleSender($sender);
                            break;
                        }
                        if (!isset($args[1]) || isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('join_help'));
                            break;
                        }
                        if (!$this->plugin->arenaExist($args[1])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                            break;
                        }
                        if ($this->plugin->arenas[$args[1]]['enable'] === false) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                            break;
                        }
                        if ($this->plugin->ins[$args[1]]->inArena($sender)) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('already_in_game'));
                            break;
                        }
                        $this->plugin->ins[$args[1]]->joinToArena($sender);
                        break;
                    case "set":
                        if (!$sender->hasPermission('sw.command.set')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $this->consoleSender($sender);
                            break;
                        }
                        if (!isset($args[1]) || isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('set_help'));
                            break;
                        }
                        if (!$this->plugin->arenaExist($args[1])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_exist'));
                            break;
                        }
                        if ($this->plugin->isArenaSet($args[1])) {
                            $a = $this->plugin->ins[$args[1]];
                            if ($a->game !== 0 || count(array_merge($a->ingamep, $a->waitingp, $a->spec)) > 0) {
                                $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_running'));
                                break;
                            }
                            $a->setup = true;
                        }
                        $this->plugin->setters[strtolower($sender->getName())]['arena'] = $args[1];
                        $sender->sendMessage($this->plugin->getMsg('enable_setup_mode'));
                        break;
                    case "reset":
                        if (count($args) > 0) {
                            if (isset($args[0])) {
                                switch ($args[0]) {
                                    case "world":
                                    //TO-DO
                                }
                            }
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('reset_help'));
                            break;
                        }
                    case "shop":
                        //TO-DO
                        break;
                    case "setlobby":
                        if (!$sender->hasPermission('sw.command.setlobby')) {
                            $sender->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (!$sender instanceof Player) {
                            $this->consoleSender($sender);
                            break;
                        }
                        if (isset($args[1]) || isset($args[2])) {
                            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('setlobby_help'));
                            break;
                        }
                        $this->plugin->setLobby($sender);
                        return true;
                    default:
                        $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('help'));
                }
                return;
            }
            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('help'));
        }
    }

    private function consoleSender($p) {
        $p->sendMessage("run command only in-game");
    }

}
