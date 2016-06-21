<?php

namespace larryTheCoder\Arena;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\entity\Effect;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\inventory\ChestInventory;
use pocketmine\level\sound\AnvilUseSound;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;

use larryTheCoder\SkyWarsAPI;
use larryTheCoder\Events\PlayerWinArenaEvent;
use larryTheCoder\Events\PlayerLoseArenaEvent;
use larryTheCoder\Events\PlayerJoinArenaEvent;

/**
 * Arena : Main Arena Class
 * 
 * @copyright (c) 2016, larryTheHarry
 * CurrentVersion: < BETA | Testing >
 * 
 */
class Arena implements Listener {

    public $data;
    private $id;

    public $plugin;
    public $game = 0; # 0 = waiting | starting, 2 = ingame,
    public $forcestart = false;
    

    public $waitingp = [];
    public $ingamep = [];
    public $spec = [];

    public $winner = [];
    public $deads = [];

    public $setup = false;

    public function __construct($id, SkyWarsAPI $plugin) {
        $this->id = $id;
        $this->plugin = $plugin;
        $this->data = $plugin->arenas[$id];
        $this->checkWorlds();
        if (strtolower($this->data['arena']['time'] !== "true")) {
            $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])->setTime(str_replace(['day', 'night'], [6000, 18000], $this->data['arena']['time']));
            $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])->stopTime();
        }
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
        $command = strtolower(substr($event->getMessage(), 0, 9));
        if ($command === "/lobby") {
            $this->onCommandProcess($event->getPlayer());
        }
    }

    /**
     * @param ServerCommandEvent $event
     */
    public function onServerCommandProcess(ServerCommandEvent $event) {
        $cmd = strtolower(substr($event->getCommand(), 0, 8));
        if ($cmd === "lobby") {
            $this->onCommandProcess($event->getSender());
        }
    }

    public function onCommandProcess(CommandSender $sender) {
        $cmd = $this->getServer()->getCommandMap()->getCommand("lobby");
        if ($cmd instanceof Command) {
        if ($this->inArena($sender) && $this->game === 1 && !$this->getPlayerMode($sender) === 1) {
            $sender->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg("in_arena"));
        }   
        }
    }

    public function giveEffect($e, Player $p) {
        $effect = Effect::getEffect($e);
        if ($e === 1) {
            $effect->setAmplifier(9);
        } else if($e === 2){
            
        }  else {
            $effect->setAmplifier(1);
        }
        $effect->setDuration(10);
        $effect->setVisible(false);
        $p->addEffect($effect);
    }

    public function getStatus() {
        if (count($this->waitingp == $this->getMaxPlayers())){
            return "full";
        }
        if ($this->game === 0){
            return "waiting";
        }
        if ($this->game === 1){
            return "ingame";
        }
    }

    public function enableScheduler() {
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new ArenaSchedule($this), 20);
    }

    public function getReward(Player $p){
        if(isset($this->data['arena']['item_reward']) && $this->data['arena']['item_reward'] !== null && intval($this->data['arena']['item_reward']) !== 0){
            foreach(explode(',', str_replace(' ', '', $this->data['arena']['item_reward'])) as $item){
                $exp = explode(':', $item);
                if(isset($exp[0]) && isset($exp[0]) && isset($exp[0])){
                    list($id, $damage, $count) = $exp;
                    if(Item::get($id, $damage, $count) instanceof Item){
                        $p->getInventory()->addItem(Item::get($id, $damage, $count));
                    }
                }
            }
        }
        if(isset($this->data['arena']['money_reward'])){
        if($this->data['arena']['money_reward'] !== null && $this->plugin->economy !== null){
            $money = $this->data['arena']['money_reward'];
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


    public function checkWinners(Player $p){
        if(count($this->ingamep) <= 3){
            $this->winners[count($this->ingamep)] = $p->getName();
        }
    }

    public function broadcastResult(){
        // TO-DO: random giveReward() to all players like brokenlens
        if($this->plugin->getServer()->getPlayer($this->winners[1]) instanceof Player){
            $this->giveReward($this->plugin->getServer()->getPlayer($this->winners[1]));
            $this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerWinArenaEvent($this->plugin, $this->plugin->getServer()->getPlayer($this->winners[1]), $this));
        }
        if(!isset($this->winners[1])) $this->winners[1] = "---";
        if(!isset($this->winners[2])) $this->winners[2] = "---";
        if(!isset($this->winners[3])) $this->winners[3] = "---";
        $vars = ['%1', '%2', '%3', '%4'];
        $replace = [$this->id, $this->winners[1], $this->winners[2], $this->winners[3]];
        $msg = str_replace($vars, $replace, $this->plugin->getMsg('end_game'));
        $levels = $this->plugin->getServer()->getDefaultLevel();
        foreach($levels as $level){
            $lvl = $this->plugin->getServer()->getLevelByName($level);
            if($lvl instanceof Level){
                foreach($lvl->getPlayers() as $p){
                    $p->sendMessage($msg);
                }
            }
        }
    }

    public function getPlayerMode(Player $p) {
        if (isset($this->waitingp[strtolower($p->getName())])) {
            return 0;
        }
        if (isset($this->ingamep[strtolower($p->getName())])) {
            return 1;
        }
        if (isset($this->spec[strtolower($p->getName())])) {
            return 2;
        }
        return false;
    }

    public function messageArenaPlayers($msg) {
        $ingame = array_merge($this->waitingp, $this->ingamep, $this->spec);
        foreach ($ingame as $p) {
            $p->sendMessage($this->plugin->getPrefix() . $msg);
        }
    }

    public function saveInv(Player $p) {
        $items = [];

        foreach ($p->getInventory()->getContents() as $slot => $item) {
            $items[$slot] = implode(":", [$item->getId(), $item->getDamage(), $item->getCount()]);
        }
        $this->plugin->inv[strtolower($p->getName())] = $items;
        $p->getInventory()->clearAll();
    }

    public function onMove(PlayerMoveEvent $e) {
        $p = $e->getPlayer();
        if ($this->inArena($p) && $this->game === 0 && $p->getGameMode() == 0) {
            $to = clone $e->getFrom();
            $to->yaw = $e->getTo()->yaw;
            $to->pitch = $e->getTo()->pitch;
            $e->setTo($to);
            return;
        }
        if ($this->game > 1) {
            $e->getHandlers()->unregister($this);
        }
    }

    public function loadInv(Player $p) {
        if (!$p->isOnline()) {
            return;
        }
        $p->getInventory()->clearAll();
        foreach ($this->plugin->inv[strtolower($p->getName())] as $slot => $i) {
            list($id, $dmg, $count) = explode(":", $i);
            $item = Item::get($id, $dmg, $count);
            $p->getInventory()->setItem($slot, $item);
            unset($this->plugin->inv[strtolower($p->getName())]);
        }
    }

    public function getMaxPlayers() {
        return $this->data['arena']['max_players'];
    }

    public function getMinPlayers() {
        return $this->data['arena']['min_players'];
    }

    public function onBlockTouch(PlayerInteractEvent $e) {
        $b = $e->getBlock();
        $p = $e->getPlayer();
        if ($p->hasPermission("sw.sign") || $p->isOp()) {
            if ($b->x == $this->data["signs"]["join_sign_x"] && $b->y == $this->data["signs"]["join_sign_y"] && $b->z == $this->data["signs"]["join_sign_z"] && $b->level == $this->plugin->getServer()->getLevelByName($this->data["signs"]["join_sign_world"])) {
                if ($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 1 || $this->getPlayerMode($p) === 2) {
                    return;
                }
                $this->joinToArena($p);
            }
            if ($b->x == $this->data["signs"]["return_sign_x"] && $b->y == $this->data["signs"]["return_sign_y"] && $b->z == $this->data["signs"]["return_sign_z"] && $b->level == $this->plugin->getServer()->getLevelByName($this->data["arena"]["arena_world"])) {
                if ($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2) {
                    $this->leaveArena($p);
                }
            }
            return;
        }
        $p->sendMessage($this->plugin->getMsg('has_not_permission'));
    }

    public function joinToArena(Player $p) {
        if ($p->hasPermission("sw.acces") || $p->isOp()) {
            if ($this->setup === true) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_in_setup'));
                return;
            }
            if (count($this->waitingp) >= $this->getMaxPlayers()) { #|| !$p->hasPermission('sw.acces.full')) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('game_full'));
                return;
            }
            if ($this->game === 1) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ingame'));
                return;
            }
            if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
                $this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
            }
            if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
                $this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
            }
            $this->plugin->getServer()->getPluginManager()->callEvent($e = new PlayerJoinArenaEvent($this->plugin, $p, $this));
            if ($e->isCancelled()) {
                return;
            }
            $this->saveInv($p);
            $this->waitingp[strtolower($p->getName())] = $p;
            $sound = new EndermanTeleportSound(new Vector3());
            $sound->setComponents($p->x, $p->y, $p->z);
            $this->level = $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
            $this->level->addSound($sound, [$p]);
            $aop = count($this->waitingp);
            $thespawn = $this->data["arena"]["spawn_positions"]["spawn" . ($aop)];
            $spawn = new Position($thespawn[0] + 0.5, $thespawn[1], $thespawn[2] + 0.5, $this->level);
            $p->teleport($spawn, 0, 0);
            $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('join'));
            $vars = ['%1'];
            $replace = [$p->getName()];
            $this->messageArenaPlayers(str_replace($vars, $replace, $this->plugin->getMsg('join_others')));
            return;
        }
        $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('has_not_permission'));
    }

    public function refillChests(Level $level) {
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Chest) {
                $chest = $t;
                $chest->getInventory()->clearAll();
                if ($chest->getInventory() instanceof ChestInventory) {
                    for ($i = 0; $i <= 26; $i++) {
                        $rand = rand(1, 3);
                        if ($rand == 1) {
                            $k = array_rand($this->plugin->cfg->get("chestitems"));
                            $v = $this->plugin->cfg->get("chestitems")[$k];
                            $chest->getInventory()->setItem($i, Item::get($v[0], $v[1], $v[2]));
                        }
                    }
                }
            }
        }
    }

    public function checkAlive() {
        if (count($this->ingamep) <= 1) {
            if (count($this->ingamep) === 1) {
                foreach ($this->ingamep as $p) {
                    $this->winners[1] = $p->getName();
                }
            }
            $this->stopGame();
        }
    }

    public function startGame() {
        $this->game = 1;
        $sound = new AnvilUseSound(new Vector3());
        foreach ($this->waitingp as $p) {
            $this->refillChests($this->level);
            unset($this->waitingp[strtolower($p->getName())]);
            $this->ingamep[strtolower($p->getName())] = $p;
            $x = $p->getFloorX();
            $y = $p->getFloorY();
            $z = $p->getFloorZ();
            $this->plugin->getServer()->getLevelByName($this->level)->setBlock(new Vector3($x, $y, $z), Block::get(0, 0));
            $sound->setComponents($p->x, $p->y, $p->z);
            $this->level->addSound($sound, [$p]);
        }
        $this->messageArenaPlayers($this->plugin->getMsg('start_game'));
    }
    // Function okay
    public function stopGame() {
        $this->unsetAllPlayers();
        $this->resetLevel($this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']));
        $this->forcestart = false;
        $this->game = 0;
        $this->plugin->getServer()->getLogger()->info($this->plugin->getPrefix().TextFormat::RED."Arena level ".TextFormat::YELLOW.$this->data['arena']['arena_world'].TextFormat::RED." has stopeed!");
    }

    private function saveWorld(Level $level) {
        if ($level instanceof Level) {
            $this->plugin->copyr($this->plugin->getServer()->getDataPath() . "/worlds/" . $level->getName(), $this->plugin->getDataFolder() . "/skywars_worlds/" . $level->getName());
        }
    }

    public function resetLevel(Level $level) {
        $levelname = $level->getName();
        if ($this->plugin->getServer()->isLevelLoaded($levelname)) {
            $this->plugin->getServer()->unloadLevel($level, true);
        }
        $this->plugin->copyr($this->plugin->getDataFolder() . "/skywars_worlds/" . $levelname, $this->plugin->getServer()->getDataPath() . "/worlds/" . $levelname);
        if(!$this->plugin->getServer()->isLevelLoaded($levelname)) {
            $this->plugin->getServer()->loadLevel($levelname);
        }
        $this->plugin->copyr($this->plugin->getServer()->getDataPath() . "/worlds/" . $levelname, $this->plugin->getDataFolder() . "/skywars_worlds/" . $levelname);
        return;
    }

    public function unsetAllPlayers() {
        foreach ($this->ingamep as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->ingamep[strtolower($p->getName())]);
            $p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
        }
        foreach ($this->waitingp as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->waitingp[strtolower($p->getName())]);
            $p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
        }
        foreach ($this->spec as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->spec[strtolower($p->getName())]);
            $p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
        }
    }

    public function inArena(Player $p) {
        $players = array_merge($this->waitingp, $this->ingamep, $this->spec);
        return isset($players[strtolower($p->getName())]);
    }

    public function onPlayerDeath(PlayerDeathEvent $e) {
        $p = $e->getEntity();

        if (!$this->inArena($p)) {
            return;
        }
        if ($p instanceof Player) {
            if ($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2) {
                $e->setDeathMessage("");
            }
            if ($this->getPlayerMode($p) === 1) {
                $this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerLoseArenaEvent($this->plugin, $p, $this));
                $e->setDeathMessage("");
                $e->setDrops([]);
                $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
                unset($this->ingamep[strtolower($p->getName())]);
                $this->spec[strtolower($p->getName())] = $p;
                foreach ($ingame as $pl) {
                    $pl->sendMessage($this->plugin->getPrefix() . str_replace(['%2', '%1'], [count($this->ingamep), $p->getName()], $this->plugin->getMsg('death')));
                }
                $this->checkAlive();
            }
        }
        $this->strikeLightning($p);
    }
    
    public function onRespawn(PlayerRespawnEvent $e) {
        $p = $e->getPlayer();
        if ($this->getPlayerMode($p) === 0) {
            $e->setRespawnPosition(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
            return;
        }
        if ($this->getPlayerMode($p) === 1) {
            if ($this->data['arena']['spectator_mode'] == 'true') {
                $e->setRespawnPosition(new Position($this->data['arena']['spec_spawn_x'], $this->data['arena']['spec_spawn_y'], $this->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
                unset($this->ingamep[strtolower($p->getName())]);
                $this->spec[strtolower($p->getName())] = $p;
                return;
            }
            unset($this->ingamep[strtolower($p->getName())]);
            $e->setRespawnPosition(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
            return;
        }
        if ($this->getPlayerMode($p) === 2) {
            $e->setRespawnPosition(new Position($this->data['arena']['spec_spawn_x'], $this->data['arena']['spec_spawn_y'], $this->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
        }
    }

    public function onHit(EntityDamageEvent $e) {
        $entity = $e->getEntity();

        $player = $entity instanceof Player;

        if ($e instanceof EntityDamageByEntityEvent || $e instanceof EntityDamageByChildEntityEvent) {
            $damager = $e->getDamager();
            if ($damager instanceof Player && $this->inArena($damager) && $this->game >= 0 && $this->game <= 1 || $player && $this->inArena($entity) && $this->game >= 0 && $this->game <= 1) {
                $e->setCancelled(true);
                return;
            }
        } else if (!$player) {
            return;
        } else if ($this->inArena($entity) && $this->game >= 0 && $this->game <= 1) {
            $e->setCancelled();
            return;
        }
    }

    /**
     * @param Player $p
     */
    public function strikeLightning(Player $p) {
        $level = $p->getLevel();
        
        $light = new AddEntityPacket();
        $light->metadata = [];
        
        $light->type = 93;
        $light->eid = Entity::$entityCount++;
        
        $light->speedX = 0;
        $light->speedY = 0;
        $light->speedZ = 0;
        
        $light->yaw = $p->getYaw();
        $light->pitch = $p->getPitch();
        
        $light->x = $p->x;
        $light->y = $p->y;
        $light->z = $p->z;
        
        Server::broadcastPacket($level->getPlayers(),$light);
    }

    public function leaveArena(Player $p) {
        $sound = new EndermanTeleportSound(new Vector3());
        $sound->setComponents($p->x, $p->y, $p->z);
        if ($this->getPlayerMode($p) == 0) {
            unset($this->waitingp[strtolower($p->getName())]);
            $this->level->addSound($sound, [$p]);
            $p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
        }
        if ($this->getPlayerMode($p) == 1) {
            if ($this->game === 0) {
                $this->checkWinners($p);
                unset($this->ingamep[strtolower($p->getName())]);
                $this->level->addSound($sound, [$p]);
                $this->messageArenaPlayers(str_replace("%1", $p->getName(), $this->plugin->getMsg('leave_others')));
                $this->checkAlive();
            } else {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('in_game'));
            }
        }
        if ($this->getPlayerMode($p) == 2) {
            unset($this->spec[strtolower($p->getName())]);
            $this->level->addSound($sound, [$p]);
            $p->teleport(new Position($this->plugin->cfg->getNested('lobby.spawn_x'), $this->plugin->cfg->getNested('lobby.spawn_y'), $this->plugin->cfg->getNested('lobby.spawn_z'), $this->plugin->getServer()->getLevelByName($this->plugin->cfg->getNested('lobby.world'))));
        }
        if (isset($this->plugin->players[strtolower($p->getName())]['arena'])) {
            unset($this->plugin->players[strtolower($p->getName())]['arena']);
        }
        if (!$this->plugin->getServer()->isLevelGenerated($this->plugin->cfg->getNested('lobby.world'))) {
            $this->plugin->getServer()->generateLevel($this->plugin->cfg->getNested('lobby.world'));
        }
        if (!$this->plugin->getServer()->isLevelLoaded($this->plugin->cfg->getNested('lobby.world'))) {
            $this->plugin->getServer()->loadLevel($this->plugin->cfg->getNested('lobby.world'));
        }
        $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('leave'));
        $this->loadInv($p);
        $p->removeAllEffects();
    }
   
    public function checkWorlds() {
        if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
        }
        if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
        }
        if (!$this->plugin->getServer()->isLevelGenerated($this->data['signs']['join_sign_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['signs']['join_sign_world']);
        }
        if (!$this->plugin->getServer()->isLevelLoaded($this->data['signs']['join_sign_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['signs']['join_sign_world']);
        }
        if (!$this->plugin->getServer()->isLevelGenerated($this->plugin->cfg->getNested('lobby.world'))) {
            $this->plugin->getServer()->generateLevel($this->plugin->cfg->getNested('lobby.world'));
        }
        if (!$this->plugin->getServer()->isLevelLoaded($this->plugin->cfg->getNested('lobby.world'))) {
            $this->plugin->getServer()->loadLevel($this->plugin->cfg->getNested('lobby.world'));
        }
        if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
        }
        if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
        }
        if (!file_exists($this->plugin->getDataFolder() . "/skywars_worlds/" . $this->data['arena']['arena_world'])) {
            $this->saveWorld($this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']));
        }
    }

}
