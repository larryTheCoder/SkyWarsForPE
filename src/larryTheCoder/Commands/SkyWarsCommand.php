<?php

namespace larryTheCoder\Commands;

use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use larryTheCoder\SkyWarsAPI;

class SkyWarsCommand {

    public function __construct(SkyWarsAPI $skywars) {
        $this->plugin = $skywars;
    }

    public function onCommand(CommandSender $p, Command $cmd, $label, array $args) {
        if (strtolower($cmd->getName()) == "skywars") {
            if (isset($args[0])) {
                switch (strtolower($args[0])) {
                    //TO-DO case "ban":
                    //TO-DO case "kick:
                    //TO-DO case "reload":
                    //TO-DO case "delete": </arena>
                    case "help":
                        if (!$p->hasPermission("sw.command.help")) {
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        $msg = "§9--- §c§lSkyWars help§l§9 ---§r§f";
                        if ($p->hasPermission('sw.command.lobby')) $msg .= $this->plugin->getMsg('lobby');
                        if ($p->hasPermission('sw.command.leave')) $msg .= $this->plugin->getMsg('onleave');
                        if ($p->hasPermission('sw.command.join')) $msg .= $this->plugin->getMsg('onjoin');
                        if ($p->hasPermission('sw.command.setlobby')) $msg .= $this->plugin->getMsg('setlobby');
                        if ($p->hasPermission('sw.command.stop')) $msg .= $this->plugin->getMsg('stop');
                        if ($p->hasPermission('sw.command.kick')) $msg .= $this->plugin->getMsg('kick');
                        if ($p->hasPermission('sw.command.set')) $msg .= $this->plugin->getMsg('set');
                        if ($p->hasPermission('sw.command.delete')) $msg .= $this->plugin->getMsg('delete');
                        if ($p->hasPermission('sw.command.create')) $msg .= $this->plugin->getMsg('create');
                        $p->sendMessage($msg);
                        break;
                    case "create":
                        if (!$p->hasPermission("sw.command.create")) {
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if (isset($args[1])) {
                            $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('create_help'));
                            break;
                        }
                        if(!($p instanceof Player)){
                            $p->sendMessage("run command in-game only");
                        }
                        if (file_exists($this->plugin->getServer()->getDataPath() . "/worlds/" . $args[1])) {
                            $this->plugin->getServer()->loadLevel($args[1]);
                            $this->plugin->getServer()->getLevelByName($args[1])->loadChunk($this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
                            array_push($this->plugin->arenas, $args[1]);
                            $this->plugin->currentLevel = $args[1];
                            $this->plugin->mode = 1;
                            $p->sendMessage($this->plugin->getPrefix() . "You are about to register an arena. Tap a block to set a spawn point there!");
                            $p->setGamemode(1);
                            $p->teleport($this->plugin->getServer()->getLevelByName($args[1])->getSafeSpawn(), 0, 0);
                        } else {
                            $p->sendMessage($this->plugin->getPrefix() . "There is no world with this name.");
                        }
                        break;
                    case "setlobby":
                        if (!$p->hasPermission('sw.command.setlobby')) {
                            $p->sendMessage($this->plugin->getMsg('has_not_permission'));
                            break;
                        }
                        if(!($p instanceof Player)){
                            $p->sendMessage("run command in-game only");
                        }
                        $this->plugin->setLobby($p);
                        return true;
                    case "setrank":
                        if (!empty($args[1])) {
                            $rank = "";
                            if ($args[0] == "VIP+") {
                                $rank = "§b[§aVIP§4+§b]";
                            } else if ($args[0] == "YouTuber") {
                                $rank = "§b[§4You§7Tuber§b]";
                            } else if ($args[0] == "YouTuber+") {
                                $rank = "§b[§4You§7Tuber§4+§b]";
                            } else {
                                $rank = "§b[§a" . $args[0] . "§b]";
                            }
                            $config = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
                            $config->set($args[1], $rank);
                            $config->save();
                            $p->sendMessage($args[1] . " got this rank: " . $rank);
                        } else {
                            $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg("setrank_help"));
                        }
                    default:
                        $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg("setrank_help"));
                        break;
                }
                return;
            }
            $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('help'));
        }
    }

}
