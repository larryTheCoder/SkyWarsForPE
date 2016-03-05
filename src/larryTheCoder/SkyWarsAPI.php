<?php

namespace larryTheCoder;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\level\Position;
use pocketmine\level\Level;
//use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Server;
//use pocketmine\OfflinePlayer;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
//use pocketmine\scheduler\PluginTask;
use larryTheCoder\task\CallBackTask;
//use pocketmine\block\Block;
//use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
//use pocketmine\tile\Tile;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use FChestReset\SkyWarsChestRST;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerMoveEvent;

class SkyWarsAPI extends PluginBase implements Listener{
    
        public $message;
    
	private static $obj = null;
        
	public static function getInstance(){
		return self::$obj;
	}
        
	public function onEnable(){
                $this->initConfig();
		if(!self::$obj instanceof Main)
		{
			self::$obj = $this;
		}
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallBackTask ([$this,"gameTimber"]),20);
		@mkdir($this->getDataFolder(), 0777, true);
		$this->config=new Config($this->getDataFolder() . "config.yml", Config::YAML, array());
                $this->getServer()->getLogger()->info($this->getMsg('config_not_created'));
		if($this->config->exists("lastpos")){
			$this->sign=$this->config->get("sign");
			$this->pos1=$this->config->get("pos1");
			$this->pos2=$this->config->get("pos2");
			$this->pos3=$this->config->get("pos3");
			$this->pos4=$this->config->get("pos4");
			$this->pos5=$this->config->get("pos5");
			$this->pos6=$this->config->get("pos6");
			$this->pos7=$this->config->get("pos7");
			$this->pos8=$this->config->get("pos8");
			$this->lastpos=$this->config->get("lastpos");
			$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
			$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
			$this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
			$this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
			$this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
			$this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
			$this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
			$this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
			$this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
			$this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
			$this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
			$this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
		}
		$this->endTime=(int)$this->config->get("endTime");
		$this->gameTime=(int)$this->config->get("gameTime");
		$this->waitTime=(int)$this->config->get("waitTime");
		$this->godTime=(int)$this->config->get("godTime");
		$this->gameStatus=0;
		$this->lastTime=0;
		$this->players=array();
		$this->SetStatus=array();
		$this->all=0;
		$this->config->save();
		$this->getServer()->getLogger()->info($this->getPrefix().$this->getMsg('game_loaded'));
        }
        
        public function initConfig(){
            if(!file_exists($this->getDataFolder())){
                @mkdir($this->getDataFolder());
            }
            if(!is_file($this->getDataFolder()."config.yml")){
                $this->saveResource("config.yml");
            }
        $this->cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
            if(!file_exists($this->getDataFolder()."arenas/")){
            @mkdir($this->getDataFolder()."arenas/");
            $this->saveResource("arenas/default.yml");
            }
            if(!file_exists($this->getDataFolder()."languages/")){
                @mkdir($this->getDataFolder()."languages/");
            }
            if(!is_file($this->getDataFolder()."languages/English.yml")){
                $this->saveResource("languages/English.yml");
            }
            if(!is_file($this->getDataFolder()."languages/Czech.yml")){
                $this->saveResource("languages/Czech.yml");
            }
            if(!is_file($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml")){
                $this->msg = new Config($this->getDataFolder()."languages/English.yml", Config::YAML);
                $this->getServer()->getLogger()->info("Selected language English");
            }
        else{
            $this->msg = new Config($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("Selected language {$this->cfg->get('Language')}");
        }
    }
        
        public function onJoin(PlayerJoinEvent $event){
            if($this->config->get('welcome-message') !== true){
                $name = $event->getPlayer()->getName();
                $event->setJoinMessage('');
		$event->getPlayer()->sendMessage(TextFormat::GREEN.'Welcome '.TextFormat::RED.$name.TextFormat::GREEN.' to SkyWarslobby!');
                $event->getPlayer()->sendMessage('§9§o>>§r---------------------------------§o<<');
                $event->getPlayer()->sendMessage('§dThis You must authenticate to play');
                $event->getPlayer()->sendMessage('      §bType your password below    ');
                $event->getPlayer()->sendMessage('§9§o>§r>---------------------------------§o<<');
                
            }
	}
        
    public function getMsg($key){
        $msg = $this->msg;
        return str_replace("&", "§", $msg->get($key));
    }
    
    
    public function getPrefix(){
        return str_replace("&", "§", $this->config->get('Prefix'));
    }
    	
	public function onPlayerRespawn(PlayerRespawnEvent $event){
        $player = $event->getPlayer();
        if($this->config->exists("lastpos"))
        {
			if($player->getLevel()->getFolderName()==$this->level->getFolderName())
			{
				$v3=$this->signlevel->getSpawnLocation();
				$event->setRespawnPosition(new Position($v3->x,$v3->y,$v3->z,$this->signlevel));
			}
		}
		unset($event,$player);
    }
    
	public function onPlace(BlockPlaceEvent $event){
		if(!isset($this->sign))
		{
			return;
		}
		$block=$event->getBlock();
		if($this->PlayerIsInGame($event->getPlayer()->getName()) || $block->getLevel()==$this->level)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($block,$event);
	}
	
	public function onMove(PlayerMoveEvent $event){
		if(!isset($this->sign))
		{
			return;
		}
		if($this->PlayerIsInGame($event->getPlayer()->getName()) && $this->gameStatus<=1)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($event);
	}
        
	public function onBreak(BlockBreakEvent $event){
		if(!isset($this->sign))
		{
			return;
		}
		$sign=$this->config->get("sign");
		$block=$event->getBlock();
		if($this->PlayerIsInGame($event->getPlayer()->getName()) || ($block->getX()==$sign["x"] && $block->getY()==$sign["y"] && $block->getZ()==$sign["z"] && $block->getLevel()->getFolderName()==$sign["level"]) || $block->getLevel()==$this->level)
		{
			if(!$event->getPlayer()->isOp())
			{
				$event->setCancelled();
			}
		}
		unset($sign,$block,$event);
	}
	
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		if(!$this->PlayerIsInGame($event->getPlayer()->getName()) || $event->getPlayer()->isOp() || substr($event->getMessage(),0,1)!="/")
		{
			unset($event);
			return;
		}
		switch(strtolower(explode(" ",$event->getMessage())[0]))
		{
		case "/lobby":
			
			break;
		default:
			$event->setCancelled();
			$event->getPlayer()->sendMessage("You are now in game,cannot use other commands");
			$event->getPlayer()->sendMessage("You can also use /lobby to exit game");
			break;
		}
		unset($event);
	}
	
	public function onDamage(EntityDamageEvent $event){
            
		$player = $event->getEntity();
		if ($event instanceof EntityDamageByEntityEvent)
		{
        	$player = $event->getEntity();
        	$killer = $event->getDamager();
			if($player instanceof Player && $killer instanceof Player)
			{
		    	if($this->PlayerIsInGame($player->getName()) && ($this->gameStatus==2 || $this->gameStatus==1))
		    	{
		    		$event->setCancelled();
		    	}
		    	if($this->PlayerIsInGame($player->getName()) && !$this->PlayerIsInGame($killer->getName()) && !$killer->isOp()){
		    		$event->setCancelled();
		    		$killer->sendMessage("PLEASE DO NOT INTERFERENCE THE GAME");
		    		$killer->kill();
		    	}
		    }
		}
		
		unset($player,$killer,$event);
	}
	
	public function PlayerIsInGame($name){
		return isset($this->players[$name]);
	}
	
	public function PlayerDeath(PlayerDeathEvent $event){
		if($this->gameStatus==3 || $this->gameStatus==4)
		{
			if(isset($this->players[$event->getEntity()->getName()]))
			{
				$this->ClearInv($event->getEntity());
				unset($this->players[$event->getEntity()->getName()]);
				if(count($this->players)>1)
				{
					$this->sendToAll("Player {$event->getEntity()->getName()} die");
					$this->sendToAll("Players :".count($this->players));
					$this->sendToAll("Last time :".$this->lastTime." sec");
				}
				$this->changeStatusSign();
			}
			
		}
	}
	
        
	public function sendTipToAll($msg){
		foreach($this->players as $pl)
		{
			$this->getServer()->getPlayer($pl["id"])->sendTip($msg);
		}
		$this->getServer()->getLogger()->info($msg);
		unset($pl,$msg);
	}	
        
	public function gameTimber(){
		if(!isset($this->lastpos) || $this->lastpos==array())
		{
			return;
		}
		if(!$this->signlevel instanceof Level)
		{
			$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
			$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
			if(!$this->signlevel instanceof Level)
			{
				return;
			}
		}
		$this->changeStatusSign();
		if($this->gameStatus==0)
		{
			$i=0;
			foreach($this->players as $key=>$val)
			{
				$i++;
				$p=$this->getServer()->getPlayer($val["id"]);
				//echo($i."\n");
				$p->setLevel($this->level);
				eval("\$p->teleport(\$this->pos".$i.");");
				unset($p);
			}
		}
		if($this->gameStatus==1)
		{
			$this->lastTime--;
			$i=0;
			foreach($this->players as $key=>$val)
			{
				$i++;
				$p=$this->getServer()->getPlayer($val["id"]);
				//echo($i."\n");
				$p->setLevel($this->level);
				eval("\$p->teleport(\$this->pos".$i.");");
				unset($p);
			}
			switch($this->lastTime)
			{
			case 1:
				$this->sendTipToAll("§6start in §b".$this->lastTime." seconds");
				break;		
			case 2:
				$this->sendTipToAll("§6start in §b".$this->lastTime." seconds");
				break;				
			case 3:
				$this->sendTipToAll("§6start in §b".$this->lastTime." seconds");
				break;					
			case 4:
				$this->sendTipToAll("§6start in §b".$this->lastTime." seconds");
				break;				
			case 5:
				$this->sendTipToAll("§6start in §b".$this->lastTime." seconds");
				break;					
			case 10:
			//case 20:
			case 30:
				$this->sendToAll("§6The game will start in §b".$this->lastTime." seconds");
				break;
			case 60:
				$this->sendToAll("§6The game will start in §b1 minute");
				break;
			case 90:
				$this->sendToAll("§6The game will start in §b1 minute 30 seconds");
				break;
			case 120:
				$this->sendToAll("§6The game will start in §b2 minutes");
				break;
			case 150:
				$this->sendToAll("§6The game will start in §b2 minutes 30 seconds");
				break;
			case 0:
				$this->gameStatus=2;
				$this->sendTipToAll("§6Go!!!!!!");
				$this->lastTime=$this->godTime;
				$this->resetChest();
				foreach($this->players as $key=>$val)
				{
					$p=$this->getServer()->getPlayer($val["id"]);;
					$p->setHealth(20);
					$p->setFood(20);
					$p->setLevel($this->level);
				}
				$this->all=count($this->players);
				break;
			}
		}
		if($this->gameStatus==2)
		{
			$this->lastTime--;
			if($this->lastTime<=0)
			{
				$this->gameStatus=3;
				$this->sendToAll("You are now longer invisible");
				$this->lastTime=$this->gameTime;
				$this->resetChest();
			}
		}
		if($this->gameStatus==3 || $this->gameStatus==4)
		{
			if(count($this->players)==1)
			{
				$this->sendToAll(" §6Congratulations! You have won the game");
				foreach($this->players as &$pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					Server::getInstance()->broadcastMessage("Congratulates to".$p->getName()."for whom that has won the game");
					$p->setLevel($this->signlevel);
					$p->getInventory()->clearAll();
					$p->setHealth(20);
					$p->teleport($this->signlevel->getSpawnLocation());
					unset($pl,$p);
				}
				$this->clearChest();
				$this->players=array();
				$this->gameStatus=0;
				$this->lastTime=0;
				return;
			}
			else if(count($this->players)==0)
			{
				Server::getInstance()->broadcastMessage("The game ends for all players have been dead");
				$this->gameStatus=0;
				$this->lastTime=0;
				$this->clearChest();
				$this->ClearAllInv();
				return;
			}
		}
		if($this->gameStatus==3)
		{
			$this->lastTime--;
			switch($this->lastTime)
			{
			case 1:
				$this->sendTipToAll("§6deathmatch start in §b1 seconds");
				break;
			case 2:
				$this->sendTipToAll("§6deathmatch start in §b2 seconds");
				break;
			case 3:
				$this->sendTipToAll("§6deathmatch start in §b3 seconds");
				break;
			case 4:
				$this->sendTipToAll("§6deathmatch start in §b4 seconds");
				break;
			case 5:
				$this->sendTipToAll("§6deathmatch start in §b5 seconds");
				break;
			case 10:
				$this->sendToAll($this->lastTime."seconds left for the death match");
				break;
			case 0:
				$this->sendToAll("the death match begins");
				foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->level);
					$p->teleport($this->lastpos);
					unset($p,$pl);
				}
				$this->gameStatus=4;
				$this->lastTime=$this->endTime;
				break;
			}
		}
		if($this->gameStatus==4)
		{
			$this->lastTime--;
			switch($this->lastTime)
			{
			case 1:
			case 2:
			case 3:
			case 4:
			case 5:
			case 10:
			//case 20:
			case 30:
				$this->sendToAll("there are ".$this->lastTime."seconds to the end of the game");
				break;
			case 0:
				$this->sendToAll("time out,the game ends");
				Server::getInstance()->broadcastMessage("[Survial Game] time out,the game ends");
				foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->signlevel);
					$p->teleport($this->signlevel->getSpawnLocation());
					$p->getInventory()->clearAll();
					$p->setHealth(20);
					unset($p,$pl);
				}
				$this->clearChest();
				//$this->ClearAllInv();
				$this->players=array();
				$this->gameStatus=0;
				$this->lastTime=0;
				break;
			}
		}
		$this->changeStatusSign();
	}
	
	public function getMoney($name){
            
		return EconomyAPI::getInstance()->myMoney($name);
	}
	
	public function addMoney($name,$money){
            
		EconomyAPI::getInstance()->addMoney($name,$money);
		unset($name,$money);
	}
	
	public function setMoney($name,$money){
            
		EconomyAPI::getInstance()->setMoney($name,$money);
		unset($name,$money);
	}
	
	public function resetChest(){
            
		SkyWarsChestRST::getInstance()->ResetChest();
	}
	
	public function clearChest(){
            
		SkyWarsChestRST::getInstance()->ClearChest();
	}
	
	public function changeStatusSign(){
            
		if(!isset($this->sign))
		{
			return;
		}
		$sign=$this->signlevel->getTile($this->sign);
		if($sign instanceof Sign)
		{
			switch($this->gameStatus)
			{
			case 0:
				$sign->setText("§7[§aJoin§7] §b:§9".count($this->players)."§9/8","§bWonderland","§eSW 1");
				break;
			case 1:
				$sign->setText("§7[§aJoin§7] §b:§9".count($this->players)."§9/8","§bWonderland","§eSW 1");
				break;
			case 2:
				$sign->setText("§7[§5Running§7] §b:§9".count($this->players)."§9/8","§bWonderland","§eSW 1");
				break;
			case 3:
				$sign->setText("§7[§5Running§7] §b:§9".count($this->players)."§9/8","§bWonderland","§eSW 1");
				break;
			case 4:
				$sign->setText("§7[§cDM§7] §b:§9".count($this->players)."§9/8","§bWonderland","§eSW 1");
				break;
			}
		}
		unset($sign);
	}
        
    private function sendCommandHelp(CommandSender $sender){
        $sender->sendMessage($this->getMsg('remove'));
        $sender->sendMessage($this->getMsg('reload'));
        $sender->sendMessage($this->getMsg('set'));
        $sender->sendMessage($this->getMsg('lobby'));
        $sender->sendMessage($this->getMsg('start'));
        $sender->sendMessage($this->getMsg('kick'));
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
	if($command->getName() == "sw"){
            if(isset($args[0])){
                if($sender instanceof Player){
                    switch(strtolower($args[0])){
                case "set":
                        if(!$sender->hasPermission("sw.command.set") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                            $sender->sendMessage($this->getMsg('has_not_permission'));
                            break;
                        }
                            if(!$this->config->exists("lastpos")){
                                $sender->sendMessage("The game was set before,please use /sw remove and try again.");
                    }
                
                    else{
                            $name=$sender->getName();
                            $this->SetStatus[$name]=0;
                            $sender->sendMessage("Please tap the status sign.");
                    }
		break;
                case "lobby":
                        if(!$this->gameStatus>=2){
                            $sender->sendMessage(TextFormat::DARK_RED."Sorry the game has started, you cant back to lobby");
                            return;
                    }
                        if(!isset($this->players[$sender->getName()])){	
                            unset($this->players[$sender->getName()]);
                                    $sender->setLevel($this->signlevel);
                                    $sender->teleport($this->signlevel->getSpawnLocation());
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('back_lobby'));
                                    $this->sendToAll(str_replace("%1", $sender->getName(), $this->plugin->getMsg('leave_others')));
                                    $this->changeStatusSign();
                                    if($this->gameStatus==1 && count($this->players)<2)
                                    {
                                            $this->gameStatus=0;
                                            $this->lastTime=0;
                                            $this->sendToAll($this->getPrefix().$this->getMsg('stop_game1'));
                                    }
                            }
                            else
                            {
                                    $sender->sendMessage($this->getMsg($this->getPrefix().'not_in_game'));
                            }
                            return true;
		
                case "remove":
                        if(!$sender->hasPermission("sw.command.remove") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                            $sender->sendMessage($this->getMsg('has_not_permission'));
                            break;
                        }
                            $this->config->remove("sign");
                            $this->config->remove("pos1");
                            $this->config->remove("pos2");
                            $this->config->remove("pos3");
                            $this->config->remove("pos4");
                            $this->config->remove("pos5");
                            $this->config->remove("pos6");
                            $this->config->remove("pos7");
                            $this->config->remove("pos8");
                            $this->config->remove("lastpos");
                            $this->config->save();
			unset($this->sign,$this->pos1,$this->pos2,$this->pos3,$this->pos4,$this->pos5,$this->pos6,$this->pos7,$this->pos8,$this->lastpos);
                            $sender->sendMessage($this->getMsg('succeeded_reload'));
                            break;
		case "start":
                        if(!$sender->hasPermission("sw.command.start") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw")){
                            $sender->sendMessage($this->getMsg('has_not_permission'));
                            break;
                        }
                            $this->sendToAll($this->getPrefix().$this->getMsg('Force_succeed'));
                            $this->gameStatus=1;
                            $this->lastTime=5;
                            break;
                    case "reload":
                        if(!$sender->hasPermission("sw.command.reload") or $sender->hasPermission("sw.command") or $sender->hasPermission("sw.command.reload")){
                            $sender->sendMessage($this->getMsg('has_not_permission'));
                            break;
                        }
                            unset($this->config);
                            @mkdir($this->getDataFolder(), 0777, true);
                            $this->config=new Config($this->getDataFolder() . "config.yml", Config::YAML, array());
                            if($this->config->exists("lastpos")){
                                    $this->sign=$this->config->get("sign");
                                    $this->pos1=$this->config->get("pos1");
                                    $this->pos2=$this->config->get("pos2");
                                    $this->pos3=$this->config->get("pos3");
                                    $this->pos4=$this->config->get("pos4");
                                    $this->pos5=$this->config->get("pos5");
                                    $this->pos6=$this->config->get("pos6");
                                    $this->pos7=$this->config->get("pos7");
                                    $this->pos8=$this->config->get("pos8");
                                    $this->lastpos=$this->config->get("lastpos");
                                    $this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
                                    $this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
                                    $this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
                                    $this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
                                    $this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
                                    $this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
                                    $this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
                                    $this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
                                    $this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
                                    $this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
                                    $this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
                                    $this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
                            }
                            if(!$this->config->exists("endTime"))
                            {
                                    $this->config->set("endTime",600);
                            }
                            if(!$this->config->exists("gameTime"))
                            {
                                    $this->config->set("gameTime",300);
                            }
                            if(!$this->config->exists("waitTime"))
                            {
                                    $this->config->set("waitTime",180);
                            }
                            if(!$this->config->exists("godTime"))
                            {   
                                    $this->config->set("godTime",15);
                            }
                       	$this->endTime=(int)$this->config->get("endTime");
                            $this->gameTime=(int)$this->config->get("gameTime");
                            $this->godTime=(int)$this->config->get("godTime");
                            $this->waitTime=(int)$this->config->get("waitTime");                         
                            $this->gameStatus=0;
                            $this->lastTime=0;
                            $this->players=array();
                            $this->SetStatus=array();
                            $this->all=0;//
                            $this->config->save();
                            $sender->sendMessage($this->getPrefix().$this->getMsg('Succeed_reload'));
                            break;
                    default:
                            return false;
                            break;
                                }
                        }
                        $sender->sendMessage($this->getMsg('use_cmd_in_game'));
                            return;
                }
                $this->sendCommandHelp($sender);
                    return true;  
                
        }
    }
        
	public function playerBlockTouch(PlayerInteractEvent $event){
		$player=$event->getPlayer();
		$username=$player->getName();
		$block=$event->getBlock();
		$levelname=$player->getLevel()->getFolderName();
		if(isset($this->SetStatus[$username]))
		{
			switch ($this->SetStatus[$username])
			{
			case 0:
				if($event->getBlock()->getID() != 63 && $event->getBlock()->getID() != 68)
				{
					$player->sendMessage(TextFormat::GREEN."[SurvivalGames] please choose a sign to click on");
					return;
				}
				$this->sign=array(
					"x" =>$block->getX(),
					"y" =>$block->getY(),
					"z" =>$block->getZ(),
					"level" =>$levelname);
				$this->config->set("sign",$this->sign);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] SIGN for condition has been created");
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] please click on the 1st spawnpoint");
				$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
				$this->sign=new Vector3($this->sign["x"],$this->sign["y"],$this->sign["z"]);
				$this->changeStatusSign();
				break;
			case 1:
				$this->pos1=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos1",$this->pos1);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] Spawnpoint 1 created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] Please click.on the 2nd spawnpoint");
				$this->pos1=new Vector3($this->pos1["x"]+0.5,$this->pos1["y"],$this->pos1["z"]+0.5);
				break;
			case 2:
				 $this->pos2=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos2",$this->pos2);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] spawnpoint 2 created");
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] Please click on the 3rd spawnpoint");
				$this->pos2=new Vector3($this->pos2["x"]+0.5,$this->pos2["y"],$this->pos2["z"]+0.5);
				break;	
			case 3:
				$this->pos3=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos3",$this->pos3);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] spawnpoint 3 created");
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] Please click on the 4th spawnpoint");
				$this->pos3=new Vector3($this->pos3["x"]+0.5,$this->pos3["y"],$this->pos3["z"]+0.5);
				break;	
			case 4:
				$this->pos4=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos4",$this->pos4);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] spawnpoint 4 created");
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] please click on the 5th spawnpoint");
				$this->pos4=new Vector3($this->pos4["x"]+0.5,$this->pos4["y"],$this->pos4["z"]+0.5);
				break;
			case 5:
				$this->pos5=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos5",$this->pos5);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN." [SurvivalGames] spawnpoint 5 created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] Please click on the 6th spawnpoint");
				$this->pos5=new Vector3($this->pos5["x"]+0.5,$this->pos5["y"],$this->pos5["z"]+0.5);
				break;
			case 6:
				$this->pos6=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos6",$this->pos6);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] spawnpoint 6 created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] Please click on the 7th spawnpoint");
				$this->pos6=new Vector3($this->pos6["x"]+0.5,$this->pos6["y"],$this->pos6["z"]+0.5);
				break;
			case 7:
				$this->pos7=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos7",$this->pos7);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] spawnpoint 7 created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] Please click on the 8th spawnpoint");
				$this->pos7=new Vector3($this->pos7["x"]+0.5,$this->pos7["y"],$this->pos7["z"]+0.5);
				break;	
			case 8:
				$this->pos8=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("pos8",$this->pos8);
				$this->config->save();
				$this->SetStatus[$username]++;
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] spawnpoint 8 created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] Please click to choose a destination for the death match");
				$this->pos8=new Vector3($this->pos8["x"]+0.5,$this->pos8["y"],$this->pos8["z"]+0.5);
				break;
			case 9:
				$this->lastpos=array(
					"x" =>$block->x,
					"y" =>$block->y,
					"z" =>$block->z,
					"level" =>$levelname);
				$this->config->set("lastpos",$this->lastpos);
				$this->config->save();
				$this->lastpos=new Vector3($this->lastpos["x"]+0.5,$this->lastpos["y"],$this->lastpos["z"]+0.5);
				unset($this->SetStatus[$username]);
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] death match destination created");
				$player->sendMessage(TextFormat::GREEN."[SurvivalGames] All settings completed and you can start a game now");
				$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);					
			}
		}
		else
		{
			$sign=$event->getPlayer()->getLevel()->getTile($event->getBlock());
			if(isset($this->lastpos) && $this->lastpos!=array() && $sign instanceof Sign && $sign->getX()==$this->sign->x && $sign->getY()==$this->sign->y && $sign->getZ()==$this->sign->z && $event->getPlayer()->getLevel()->getFolderName()==$this->config->get("sign")["level"])
			{
				if(!$this->config->exists("lastpos"))
				{
					$event->getPlayer()->sendMessage("[SurvivalGames] You can not join the game for the game hasn't been set yet");
					return;
				}
				if(!$event->getPlayer()->hasPermission("FSurvivalGame.touch.startgame"))
				{
					$event->getPlayer()->sendMessage("[SurvivalGames] You don't have permission to join this game");
					return;
				}
				if(!$event->getPlayer()->isOp())
				{
					$inv=$event->getPlayer()->getInventory();
					for($i=0;$i<$inv->getSize();$i++)
    				{
    					if($inv->getItem($i)->getID()!=0)
    					{
    						$event->getPlayer()->sendMessage("[SurvivalGames] Before the game,please put things in your bag to your case");
    						return;
    					}
    				}
    				foreach($inv->getArmorContents() as $i)
    				{
    					if($i->getID()!=0)
    					{
    						$event->getPlayer()->sendMessage("[SurvivalGames] Please take off your equipments and put them in the case");
    						return;
    					}
    				}
    			}
				if($this->gameStatus==0 || $this->gameStatus==1)
				{
					if(!isset($this->players[$event->getPlayer()->getName()]))
					{
						if(count($this->players)>=6)
						{
							$event->getPlayer()->sendMessage("[SurvivalGames] the map is full,no spare place for more");
							return;
						}
						$this->sendToAll("[SurvivalGames]Player".$event->getPlayer()->getName()."joined the game");
						$this->players[$event->getPlayer()->getName()]=array("id"=>$event->getPlayer()->getName());
						$event->getPlayer()->sendMessage("[SurvivalGames] joined the game successfully");
						if($this->gameStatus==0 && count($this->players)>=2)
						{
							$this->gameStatus=1;
							$this->lastTime=$this->waitTime;
							$this->sendToAll("[SurvivalGames] The game will countdown when reach the lowest people amount level");
						}
						if(count($this->players)==8 && $this->gameStatus==1 && $this->lastTime>5)
						{
							$this->sendToAll("[SurvivalGames] Already full,starting");
							$this->lastTime=5;
						}
						$this->changeStatusSign();
					}
					else
					{
						$event->getPlayer()->sendMessage("[SurvivalGames] You are already in,input/lobby   may exit from the game");
					}
				}
				else
				{
					$event->getPlayer()->sendMessage("[SurvivalGames] Can not join the game for it has already started");
				}
			}
		}
	}
	
	public function ClearInv($player){
		if(!$player instanceof Player)
		{
			unset($player);
			return;
		}
		$inv=$player->getInventory();
		if(!$inv instanceof Inventory)
		{
			unset($player,$inv);
			return;
		}
		$inv->clearAll();
		unset($player,$inv);
	}
        
	public function ClearAllInv(){
		foreach($this->players as $pl)
		{
			$player=$this->getServer()->getPlayer($pl["id"]);
			if(!$player instanceof Player)
			{
				continue;
			}
			$this->ClearInv($player);
		}
		unset($pl,$player);
	}
	
	public function PlayerQuit(PlayerQuitEvent $event){
		if(isset($this->players[$event->getPlayer()->getName()]))
		{	
			unset($this->players[$event->getPlayer()->getName()]);
			$this->ClearInv($event->getPlayer());
			$this->sendToAll("Player".$event->getPlayer()->getName()."has left the game");
			$this->changeStatusSign();
			if($this->gameStatus==1 && count($this->players)<2)
			{
				$this->gameStatus=0;
				$this->lastTime=0;
				$this->sendToAll("Not enough players,stop counting down");
				/*foreach($this->players as $pl)
				{
					$p=$this->getServer()->getPlayer($pl["id"]);
					$p->setLevel($this->signlevel);
					$p->teleport($this->signlevel->getSpawnLocation());
					unset($p,$pl);
				}*/
			}
		}
	}
	
	public function onDisable(){
	}
}
