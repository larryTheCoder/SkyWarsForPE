<?php

namespace larryTheCoder;

use pocketmine\event\block\BlockBreakEvent;
use larryTheCoder\Task\GameSender;
use larryTheCoder\Command\SkyWarsCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use larryTheCoder\Task\RefreshSigns;
use pocketmine\level\Position;

class SkyWarsAPI extends PluginBase implements Listener{

    public $config;
    public $msg;
    public $setters = [];
    public $arenas = [];

    public function onEnable(){
        $this->initConfig();
                    $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
                    $this->swCommand = new SkyWarsCommand($this);
                    if($config->get("arenas")!=null){
                        
			$this->arenas = $config->get("arenas");
		}
		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
		}
		$items = array(array(261,0,1),array(262,0,2),array(262,0,3),array(267,0,1),array(268,0,1),array(272,0,1),array(276,0,1),array(283,0,1));
		if($config->get("chestitems")==null)
		{
			$config->set("chestitems",$items);
		}
		$config->save();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10); 
        $this->getLogger()->info(TextFormat::GREEN . "Skywars Loaded!");
    }

    public function initConfig(){
        if(!file_exists($this->getDataFolder())){
            @mkdir($this->getDataFolder());
        }
        if(!is_file($this->getDataFolder()."config.yml")){
            $this->saveResource("config.yml");
        }
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
        if(!file_exists($this->getDataFolder()."languages/")){
            @mkdir($this->getDataFolder()."languages/");
        }
        if(!is_file($this->getDataFolder()."languages/English.yml")){
                $this->saveResource("languages/English.yml");
        }
        if(!is_file($this->getDataFolder()."languages/Czech.yml")){
                $this->saveResource("languages/Czech.yml");
        }
        if(!is_file($this->getDataFolder()."languages/{$this->config->get('Language')}.yml")){
            $this->msg = new Config($this->getDataFolder()."languages/English.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language English");
        }
        else{
            $this->msg = new Config($this->getDataFolder()."languages/{$this->config->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->config->get('Language')}");
        }
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
	$this->swCommand->onCommand($sender, $command, $label, $args);
    }
    
    
    public function onBlockBreak(BlockBreakEvent $e){
        $p = $e->getPlayer();
        if(isset($this->setters[strtolower($p->getName())]['arena']) && isset($this->setters[strtolower($p->getName())]['type'])){
            $e->setCancelled(true);
            $b = $e->getBlock();
            if($this->setters[strtolower($p->getName())]['type'] == "mainlobby"){
                $this->config->setNested("lobby.x", $b->x);
                $this->config->setNested("lobby.y", $b->y);
                $this->config->setNested("lobby.z", $b->z);
                $this->config->setNested("lobby.world", $b->level->getName());
                $p->sendMessage($this->getPrefix().$this->getMsg('mainlobby'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
        }
    }
    
    public function getMsg($key){
        $msg = $this->msg;
        return str_replace("&", "Â§", $msg->get($key));
    }
    
    public function getPrefix(){
        return str_replace("&", "Â§", $this->config->get('Prefix'));
    }
    
    public function onInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		
		if($tile instanceof Sign) {
			if($this->mode==26){
				$tile->setText(TextFormat::AQUA . "[Join]",TextFormat::YELLOW  . "0 / 24",$this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "The arena has been registered successfully!");
		}
	else
		{
			$text = $tile->getText();
			if($text[3] == $this->prefix){
				if($text[0]==TextFormat::AQUA . "[Join]")
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$level = $this->getServer()->getLevelByName($text[2]);
			$aop = count($level->getPlayers());
                        $thespawn = $config->get($text[2] . "Spawn" . ($aop+1));
			$spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$level);
			$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			$player->setNameTag($player->getName());
			$player->getInventory()->clearAll();
			$config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
			$rank = $config2->get($player->getName());
			if($rank == "Â§b[Â§aVIPÂ§4+Â§b]"){
				$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
				$player->getInventory()->setHelmet(Item::get(Item::CHAIN_HELMET));
				$player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
				$player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
                                $player->getInventory()->setBoots(Item::get(Item::CHAIN_BOOTS));
				$player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
				$player->getInventory()->setHotbarSlotIndex(0, 0);
                        }
	else 
                        if($rank == "Â§b[Â§aVIPÂ§b]"){
				$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
				$player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
				$player->getInventory()->setLeggings(Item::get(Item::LEATHER_PANTS));
							$player->getInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
						else if($rank == "Â§b[Â§4YouÂ§7TuberÂ§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::GOLD_LEGGINGS));
							$player->getInventory()->setBoots(Item::get(Item::GOLD_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
						else if($rank == "Â§b[Â§aVIPÂ§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::DIAMOND_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
							$player->getInventory()->setBoots(Item::get(Item::DIAMOND_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
					}
					else
					{
						$player->sendMessage($this->prefix . "You can not join this match.");
					}
				}
			}
		}
		else if($this->mode>=1&&$this->mode<=24)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+2,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " has been registered!");
			$this->mode++;
			if($this->mode==25)
			{
				$player->sendMessage($this->prefix . "Now tap on a deathmatch spawn.");
			}
			$config->save();
		}
		else if($this->mode==25)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$level = $this->getServer()->getLevelByName($this->currentLevel);
			$level->setSpawn(new Vector3($block->getX(),$block->getY()+2,$block->getZ()));
			$config->set("arenas",$this->arenas);
			$player->sendMessage($this->prefix . "You've been teleported back. Tap a sign to register it for the arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			$config->save();
			$this->mode=26;
		}
        }
}
