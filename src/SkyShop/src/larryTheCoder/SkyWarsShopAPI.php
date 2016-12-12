<?php

/*
 * SkyWarsShopAPI
 * Copyright (C) 2013-2016  larryTheHarry <larryPeter132@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace larryTheCoder;

use larryTheCoder\SkyWarsAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\Plugin;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;

/**
 * SkyWarsShopAPI: SkyWars shopping tools
 * 
 * @copyright (c) 2016, larryTheHarry
 * this SkyWarsShopAPI <SWshop> as EconomyShop
 */
class SkyWarsShopAPI extends PluginBase implements Listener {

    /** @var array */
    private $shop;

    /** @var Config */
    private $shopSign;
    private $placeQueue;

    /** @var SkyWarsShopAPI */
    private static $instance;
    public $economy;

    public function onEnable() {
        @mkdir($this->getDataFolder());

        $this->saveDefaultConfig();

        $this->shop = (new Config($this->getDataFolder() . "Shops.yml", Config::YAML))->getAll();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->prepareLangPref();
        $this->placeQueue = [];

        self::$instance = $this;
        // Check if MAIN plugin is loaded
        if ($this->checkMainActivityIsLoaded() !== false) {
            $this->getServer()->getLogger()->info($this->getPrefix() . $this->getMsg('Economy_loaded'));
            $this->economy = SkyWarsAPI::getInstance()->economy;
            return;
        }
        // Disable plugin after MAIN plugin isn't loaded/enabled
        $this->getServer()->getPluginManager()->disablePlugin($this);
    }

    public function onDisable() {
        $config = (new Config($this->getDataFolder() . "Shops.yml", Config::YAML));
        $config->setAll($this->shop);
        $config->save();
        $this->getServer()->getLogger()->info($this->getPrefix() . TextFormat::RED . "SkyWarsShop has been disabled");
    }

    public function prepareLangPref() {
        $this->shopSign = new Config($this->getDataFolder() . "ShopText.yml", Config::YAML, yaml_parse(stream_get_contents($resource = $this->getResource("ShopText.yml"))));
        \fclose($resource);
    }

    public function getMsg($key) {
        return SkyWarsAPI::getInstance()->getMsg($key);
    }

    public function getPrefix() {
        $ins = $this->getServer()->getPluginManager()->getPlugin('SkyWarsForPE');
        if ($ins instanceof Plugin && $ins->isEnabled()) {
            return SkyWarsAPI::getInstance()->getPrefix();
        } else {
            return '§c[§r§6Sky§bWars§c]§r ';
        }
    }

    public function tagExists($tag) {
        foreach ($this->shopSign->getAll() as $key => $val) {
            if ($tag == $key) {
                return $val;
            }
        }
        return false;
    }

    /**
     * @return SkyWarsShop
     */
    public static function getInstance() {
        return self::$instance;
    }

    private function checkMainActivityIsLoaded() {
        // TO-DO support this plugin for all skywars plugin
        $main = ["SkyWarsForPE"];
        foreach ($main as $plugin) {
            $ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
            if ($ins instanceof Plugin && $ins->isEnabled()) {
                return;
            }
        }
        $this->getServer()->getLogger()->info($this->getPrefix() . 'Couldn\'t find a vilad SkyWarsForPE plugin! Stopping the plugin!');
        return false;
    }

    /**
     * This is the sign tools
     * 
     * @param SignChangeEvent $e
     */
    public function onSignChange(SignChangeEvent $e) {
        $result = $this->tagExists($e->getLine(0));
        if ($result !== false) {
            $p = $e->getPlayer();
            if (!$p->hasPermission("SkyShopPE.shop.create")) {
                $p->sendMessage($this->getMsg("has_not_permission"));
                return;
            }
            if (!is_numeric($e->getLine(1)) or ! is_numeric($e->getLine(3))) {
                $p->sendMessage($this->getPrefix() . $this->getMsg("wrong-format"));
                return;
            }
            $item = Item::fromString($e->getLine(2));
            if ($item === false) {
                $p->sendMessage($this->getMessage("item-not-support", array($e->getLine(2), "", "")));
                return;
            }
            $block = $e->getBlock();
            $this->shop[$block->getX() . ":" . $block->getY() . ":" . $block->getZ() . ":" . $block->getLevel()->getFolderName()] = array(
                "x" => $block->getX(),
                "y" => $block->getY(),
                "z" => $block->getZ(),
                "level" => $block->getLevel()->getFolderName(),
                "price" => (int) $e->getLine(1),
                "item" => (int) $item->getID(),
                "itemName" => $item->getName(),
                "meta" => (int) $item->getDamage(),
                "amount" => (int) $e->getLine(3)
            );
            $p->sendMessage();

            $e->setLine(0, $result[0]); // TAG
            $e->setLine(1, str_replace("%1", $e->getLine(1), $result[1])); // PRICE
            $e->setLine(2, str_replace("%2", $item->getName(), $result[2])); // ITEM NAME
            $e->setLine(3, str_replace("%3", $e->getLine(3), $result[3])); // AMOUNT
        }
    }

    public function getMoney($p) {
        $ec = SkyWarsAPI::getInstance()->economy;
        switch ($ec->getName()) {
            case "EconomyAPI":
                $ec->myMoney($p);
                break;
            case "PocketMoney":
                $ec->getMoney($p->getName());
                break;
            case "MassiveEconomy":
                $ec->getMoney($p->getName());
                break;
            case "GoldStd":
                $ec->getMoney($p->getName());
                break;
        }
    }
    
    public function reduceMoney($p) {
        $ec = SkyWarsAPI::getInstance()->economy;
        switch ($ec->getName()) {
            case "EconomyAPI":
                $ec->myMoney($p);
                break;
            case "PocketMoney":
                $ec->getMoney($p->getName());
                break;
            case "MassiveEconomy":
                $ec->getMoney($p->getName());
                break;
            case "GoldStd":
                $ec->getMoney($p->getName());
                break;
        }
    }

    public function onPlayerTouch(PlayerInteractEvent $e) {
        if ($e->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }
        $b = $e->getBlock();
        $loc = $b->getX() . ":" . $b->getY() . ":" . $b->getZ() . ":" . $b->getLevel()->getFolderName();
        if (isset($this->shop[$loc])) {
            $shop = $this->shop[$loc];
            $p = $e->getPlayer();
            if (!$p->hasPermission("SkyShopPE.shop.buy") ) {
                $p->sendMessage($this->getMsg("no-permission-buy"));
                $e->setCancelled();
                return;
            }
            if ($shop["price"] > $this->getMoney($p)) {
                $p->sendMessage($this->getPrefix() . $this->getMsg("no-money-buy", [$shop["item"] . ":" . $shop["meta"], $shop["price"], "%3"]));
                $e->setCancelled(true);
                if ($e->getItem()->canBePlaced()) {
                    $this->placeQueue[$p->getName()] = true;
                }
                return;
            } else {
                if (!isset($shop["itemName"])) {
                    $item = $this->getItem($shop["item"], $shop["meta"], $shop["amount"]);
                    if ($item === false) {
                        $item = $shop["item"] . ":" . $shop["meta"];
                    } else {
                        $item = $item[0];
                    }
                    $this->shop[$loc]["itemName"] = $item;
                    $shop["itemName"] = $item;
                }
                $now = microtime(true);
                if ($this->getConfig()->get("enable-double-tap")) {
                    if (!isset($this->tap[$p->getName()]) or $now - $this->tap[$p->getName()][1] >= 3.5 or $this->tap[$p->getName()][0] !== $loc) {
                        $this->tap[$p->getName()] = [$loc, $now];
                        $p->sendMessage($this->getMessage("tap-again", [$shop["itemName"], $shop["price"], $shop["amount"]]));
                        return;
                    } else {
                        unset($this->tap[$p->getName()]);
                    }
                }
            }
        }
    }

}
