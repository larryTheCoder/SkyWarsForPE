<?php

namespace larryTheCoder;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerChatEvent;
use larryTheCoder\Command\SkyWarsCMD;

class SkyWarsAPI extends PluginBase implements Listener{
    private static $obj = null;
    private $config;
    /** @var  SkyWarsCMD */
    private $mainCommand;

    public static function getInstance()
	{
		return self::$obj;
	}
	public function onEnable(){
            
		if(!self::$obj instanceof SkyWarsAPI){
                    
			self::$obj = $this;
		}
                $this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"gameTimber"]),20);
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
                if(!$this->config->exists("Language"))
                {
                    $this->config->set("Language","English");
                }
                if(!$this->config->exists("Prefix"))
                {
                    $this->config->set("Prefix"," &9[ &cSkyWars &9] ");  
                }
                if(!$this->config->exists("endTime"))
		{
			$this->config->set("endTime",180);
		}
		if(!$this->config->exists("gameTime"))
		{
			$this->config->set("gameTime",240);
		}
		if(!$this->config->exists("waitTime"))
		{
			$this->config->set("waitTime",60);
		}
		if(!$this->config->exists("godTime"))
		{
			$this->config->set("godTime",60);
		}
                if(!$this->config->exists("chat-format"))
                {
                        $this->config->set("chat-format");
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
                 $this->getServer()->getLogger()->info($this->getPrefix().("§bSkyWars has been loaded!"));
        }
       /**
         * @return \larryTheCoder\command\SkyWarsCMD
         */
        public function getMainCommand(){
            return $this->mainCommand;
        }
        public function initConfig(){
        if(!file_exists($this->getDataFolder())){
            @mkdir($this->getDataFolder());
        }
        if(!is_file($this->getDataFolder()."config.yml")){
            $this->saveResource("config.yml");
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
	
        
        public function gameTimber(){
	if(!isset($this->lastpos) || $this->lastpos==array()){
		return;
	}
	if(!$this->signlevel instanceof Level){
		$this->level=$this->getServer()->getLevelByName($this->config->get("pos1")["level"]);
		$this->signlevel=$this->getServer()->getLevelByName($this->config->get("sign")["level"]);
	if(!$this->signlevel instanceof Level)
	    {
		return;
	    }
		}
	        $this->changeStatusSign();
	if(!$this->gameStatus==0){
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
        if(!$this->gameStatus==1){
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
					$p->setLevel($this->level);
				}
				$this->all=count($this->players);
				break;
			}
		}
        if(!$this->gameStatus==3){
            $this->lastTime--;
		if($this->lastTime<=0)
		{
			$this->gameStatus=3;
			$this->sendToAll($this->getPrefix()."§6Game has been started,do OR die!");
			$this->lastTime=$this->gameTime;
			$this->resetChest();
	    }	  
        }    
        
        
        }
        
        public function getPrefix() {
            return str_replace("&", "§", $this->config->get('Prefix'));
        }
        
        public function onChat(PlayerChatEvent $event){
        $player = strtolower($event->getPlayer()->getName());
        if($this->getConfig()->get('chat-format') == true){
		$event->setFormat("§c[§bNewbie§c]§a".$player."§7§l>§f§r".$event->getMessage());
		}
	}
     /**
      * @param $kit
      * @return Kit|null
      */
        public function getKit($kit){
            /**@var Kit[] $lowerKeys*/
        $lowerKeys = array_change_key_case($this->kits, CASE_LOWER);
        if(isset($lowerKeys[strtolower($kit)])){
            return $lowerKeys[strtolower($kit)];
        }
        return null;
    }

}