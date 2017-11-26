<?php

namespace larryTheCoder\commands;

use larryTheCoder\SkyWarsPE;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\Config;

/**
 * The default SkyWars command for PocketMine-MP
 *
 * @package larryTheCoder\commands
 */
final class SkyWarsCommand {

	private $plugin;

	public function __construct(SkyWarsPE $e) {
		$this->plugin = $e;
	}

	public function onCommand(CommandSender $sender, Command $cmd, array $args): bool {
		switch ($cmd->getName()) {
			case "leave":
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
						$msg = "§9--- §c§lSkyWars help§l§9 ---§r§f";
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
						if ($sender->hasPermission('sw.command.random')) {
							$msg .= $this->plugin->getMsg('random');
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
							$msg .= $this->plugin->getMsg('reload');
						}
						if ($sender->hasPermission('sw.command.setlobby')) {
							$msg .= $this->plugin->getMsg('setlobby');
						}
						$sender->sendMessage($msg);
						break;
					case "random":
						if (!$sender->hasPermission("sw.command.random")) {
							$sender->sendMessage($this->plugin->getMsg('has_not_permission'));
							break;
						}
						if (!$sender instanceof Player) {
							$this->consoleSender($sender);
							break;
						}
						# choose which arena randomly by int
						$arena = mt_rand(0, count($this->plugin->ins));
						$this->plugin->ins[$arena]->joinToArena($sender);
						break;
					case "reload":
						if (!$sender->hasPermission("sw.command.reload")) {
							$sender->sendMessage($this->plugin->getMsg("has_not_permission"));
						}
						$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('reloading'));
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
							$this->plugin->ins[$args[1]]->startGame();
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
						$this->plugin->getPlayerArena($sender)->startGame();
						$sender->sendMessage($this->plugin->getPrefix() . "§bArena has been started!");
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
							if ($this->plugin->ins[$args[1]]->game !== 1) {
								$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_running'));
								break;
							}
							$this->plugin->ins[$args[1]]->stopGame();
							break;
						}
						if (!$sender instanceof Player) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('stop_help'));
							break;
						}
						if ($this->plugin->getPlayerArena($sender)->game !== 1) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_doesnt_running'));
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
					// TO-DO: improve /kick
					case "ban": // /ban [Player Name]
						if (!$sender->hasPermission('sw.command.ban')) {
							$sender->sendMessage($this->plugin->getMsg('has_not_permission'));
							break;
						}
						if (!isset($args[1]) || isset($args[2])) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ban_help'));
							break;
						}
						if (!file_exists($this->plugin->getDataFolder() . "players/{$args[1]}.yml")) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('no_player_file'));
							break;
						}
						$file = new Config($this->plugin->getDataFolder() . "players/{$args[1]}.yml", Config::YAML);
						if ($file->get("ban") === true) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('player_already_banned'));
							break;
						}
						$file->set("ban", true);
						$file->save();
						$sender->sendMessage(str_replace(["%1"], [$args[1]], $this->plugin->getPrefix() . $this->plugin->getMsg('player_has_banned')));
						break;
					case "unban": // /unban [Player Name]
						if (!$sender->hasPermission('sw.command.unban')) {
							$sender->sendMessage($this->plugin->getMsg('has_not_permission'));
							break;
						}
						if (!isset($args[1]) || isset($args[2])) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ban_help'));
							break;
						}
						if (!file_exists($this->plugin->getDataFolder() . "players/{$args[1]}.yml")) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('no_player_file'));
							break;
						}
						$file = new Config($this->plugin->getDataFolder() . "players/{$args[1]}.yml", Config::YAML);
						if ($file->get("ban") === false) {
							$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('player_already_unbanned'));
							break;
						}
						$file->set("ban", false);
						$file->save();
						$sender->sendMessage(str_replace(["%1"], [$args[1]], $this->plugin->getPrefix() . $this->plugin->getMsg('player_has_unbanned')));
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
							if ($a->game !== 0 || count(array_merge($a->players, $a->spec)) > 0) {
								$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ingame'));
								break;
							}
							$a->setup = true;
						}
						$this->plugin->setters[strtolower($sender->getName())]['arena'] = $args[1];
						$sender->sendMessage($this->plugin->getMsg('enable_setup_mode'));
						return true;
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
				return true;
			}
			$sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('help'));
		}
		return true;
	}

	private function consoleSender(CommandSender $p) {
		$p->sendMessage("run command only in-game");
	}

}
