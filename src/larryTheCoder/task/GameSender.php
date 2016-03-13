<?php

namespace larryTheCoder\task;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
  
class GameSender extends PluginTask {
    public $prefix = TextFormat::BLACK. "[" . TextFormat::YELLOW . TextFormat::BOLD . "Sky" . TextFormat::YELLOW . "Wars" . TextFormat::RESET . TextFormat::BLACK . "] ";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $levelArena->getPlayers();
					if(count($playersArena)==0)
					{
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 20);
					}
					else
					{
						if(count($playersArena)>=2)
						{
							if($timeToStart>0)
							{
                                                            
                                                            
                        
                                                            
                                                            
                                                            
                                                            
								$timeToStart--;
								foreach($playersArena as $pl)//sendPopup()
								{
									$pl->sendPopup(TextFormat::GOLD . $timeToStart . " seconds");
								}
								if($timeToStart<=0)
								{
									$this->refillChests($levelArena);
								}
								$config->set($arena . "StartTime", $timeToStart);
							}
							else
							{
								$aop = count($levelArena->getPlayers());
								if($aop==1)
								{
									foreach($playersArena as $pl)
									{
									    $p1->giveMoney(money);
										$pl->sendMessage($this->prefix . TextFormat::GREEN . "You won!");
										$pl->getInventory()->clearAll();
										$pl->removeAllEffects();
										$pl->setNameTag($pl->getName());
										$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
										$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
										$pl->teleport($spawn,0,0);
									    $this->getServer()->unloadLevel($this->getServer()->getLevelByName("SkyWars"));
										$this->getServer()->loadLevel($this->get("SkyWars"));
									}
									$config->set($arena . "PlayTime", 780);
									$config->set($arena . "StartTime", 20);
								}
								$time--;
								if($time>=180)
								{
								$time2 = $time - 180;
								$minutes = $time2 / 20;
								if(is_int($minutes) && $minutes>0)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage($this->prefix . $minutes . " minutes to deathmatch");
									}
								}
								else if($time2 == 300)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage($this->prefix . "Kill your opponets fast!");
									}
									$this->refillChests($levelArena);
								}
								else if($time2 == 30 || $time2 == 15 || $time2 == 10 || $time2 ==5 || $time2 ==4 || $time2 ==3 || $time2 ==2 || $time2 ==1)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage($this->prefix . $time2 . " seconds to deathmatch");
									}
								}
								if($time2 <= 0)
								{
									$spawn = $levelArena->getSafeSpawn();
									$levelArena->loadChunk($spawn->getX(), $spawn->getZ());
									foreach($playersArena as $pl)
									{
										$pl->teleport($spawn,0,0);
									}
								}
								}
								else
								{
									$minutes = $time / 20;
									if(is_int($minutes) && $minutes>0)
									{
										foreach($playersArena as $pl)
										{
											$pl->sendMessage($this->prefix . $minutes . " minutes remaining");
										}
									}
									else if($time == 30 || $time == 15 || $time == 10 || $time ==5 || $time ==4 || $time ==3 || $time ==2 || $time ==1)
									{
										foreach($playersArena as $pl)
										{
											$pl->sendMessage($this->prefix . $time . " seconds remaining");
										}
									}
									if($time <= 0)
									{
										$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
										$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
										foreach($playersArena as $pl)
										{
											$pl->teleport($spawn,0,0);
											$pl->sendMessage($this->prefix . "No winner this time!");
											$pl->getInventory()->clearAll();
										}
										$time = 780;
									}
								}
								$config->set($arena . "PlayTime", $time);
							}
						}
						else
						{
							if($timeToStart<=0)
							{
								foreach($playersArena as $pl)
								{
									$pl->getInventory()->clearAll();
									$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
									$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
									$pl->teleport($spawn);
								}
								$config->set($arena . "PlayTime", 780);
								$config->set($arena . "StartTime", 20);
							}
							else
							{
								foreach($playersArena as $pl){
								$pl->sendPopup(TextFormat::BLACK. "[" . TextFormat::YELLOW . TextFormat::BOLD . "Sky" . TextFormat::YELLOW . "Wars" . TextFormat::RESET . TextFormat::BLACK . "] " . TextFormat::GRAY."needs more players!");                             
								}
								$config->set($arena . "PlayTime", 780);
								$config->set($arena . "StartTime", 20);
							}
						}
					}
				}
			}
		}
		$config->save();
	}
	
	public function refillChests(Level $level)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Chest) 
			{
				$chest = $t;
				$chest->getInventory()->clearAll();
				if($chest->getInventory() instanceof ChestInventory)
				{
					for($i=0;$i<=26;$i++)
					{
						$rand = rand(1,3);
						if($rand==1)
						{
							$k = array_rand($config->get("chestitems"));
							$v = $config->get("chestitems")[$k];
							$chest->getInventory()->setItem($i, Item::get($v[0],$v[1],$v[2]));
						}
					}									
				}
			}
		}
	}
}