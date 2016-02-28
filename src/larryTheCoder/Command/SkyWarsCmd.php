<?php

namespace larryTheCoder\Command;



class SkyWarsCMD extends Command implements PluginIdentifiableCommand{
        public function __construct(SkyWarsAPI $plugin){
        parent::__construct(
            "skywars", 
            "Shows all the sub-commands for SkyWars", 
            "/easymessages <sub-command> [parameters]", 
            array("sw")
        );
        $this->setPermission("sw.command");
        $this->plugin = $plugin;
    }
    public function execute(CommandSender $sender, Command $command, $label, array $args){
        switch(strtolower($command->getName())){
			case "kit":
                            switch($command->getName()){
				case "addkit":
				case "add":
					$this->name = $name;
					$this->price = $price;
					$this->level = $level;
					if($this->kit->add($name, $price, $level) === true) {
					$sender->sendMesage($this->getPrefix().("added".$name."kit!"));
					} else {
					$sender->sendMesage($this->getPrefix().("Failed to add".$name."kit!"));
					}
					break;
				case "removekit":
				case "remove":
				case "rmkit":
				case "rm":
					$kitname = trim(array_shift($params));
					if ($this->isAlnum($kitname) === false) {
						$output .= FORMAT_YELLOW."[HungerGames] You need to use English for kit name.";
						break;
					}
					if ($this->kit->remove($kitname)) {
						$output .= FORMAT_DARK_AQUA."[HungerGames] Removed \"$kitname\"!\n";
					} else {
						$output .= FORMAT_YELLOW."[HungerGames] Failed to remove \"$kitname\"!\n";
					}
                                        $sender->sendMessage($output);
					break;
				case "list":
					$this->kit->showList($output);
					break;
				case "info":
						$kitname = array_shift($params);
						$this->kit->showKitInfo($kitname);
						break;
					case "additem":
                                            if($args[0]){
                                            //$count = array_shift($params);						
                                            //id = array_shift($params);
					    //$meta = array_shift($params);
					    //$kitname = array_shift($params);
                                            $this->sendMessage($this->getPrefix().("/kit additem <kit> <id> (meta) (count)"));
                                            break;
                                            }
						if (empty($kitname) or $id === null or !is_numeric($id)) {
							$output .= "Usage: /kit additem <kit> <id> (meta) (count)\n";
							break;
						}
						if ($this->kit->get($kitname) === false) {
							$output .= FORMAT_YELLOW."[HungerGames] The kit \"$kitname\" doesn't exist.\n";
							break;
						}
						if (!isset(Block::$class[$id]) and !isset(Item::$class[$id])) {
							$output .= FORMAT_YELLOW."[HungerGames]NOTICE: The item id \"$id\" could be incorrect.\n";
						}
						if ($meta === null) {
							$meta = 0;
						}
						if ($count === null) {
							$count = 1;
						}
						$sets = array("id" => $id, "meta" => $meta, "count" => $count);
						if ($this->kit->editItem("add", $kitname, $sets)) {
							$output .= FORMAT_DARK_AQUA."[HungerGames] Added items to \"$kitname\"!\n";
						} else {
							$output .= FORMAT_YELLOW."[HungerGames] Failed to add items to \"$kitname\".\n";
						}
						$this->kit->showKitInfo($kitname);
						break;
				}
                        }
        }

}