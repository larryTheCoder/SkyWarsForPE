<?php

namespace larryTheCoder;

use pocketmine\Player;
use larryTheCoder\SkyWarsAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;

class SkyWarsListener implements Listener {

    private $plugin;

    public function __construct(SkyWarsAPI $plugin) {
        $this->plugin = $plugin;
    }

    public function onLevelChange(EntityLevelChangeEvent $ev) {
        if ($ev->getEntity() instanceof Player) {
            foreach ($this->plugin->ins as $a) {
                if ($a->inArena($ev->getEntity()->getName())) {
                    $ev->setCancelled();
                    break;
                }
            }
        }
    }

    public function onTeleport(EntityTeleportEvent $ev) {
        if ($ev->getEntity() instanceof Player) {
            foreach ($this->plugin->ins as $a) {
                if ($a->inArena($ev->getEntity()->getName())) {
                    //Allow near teleport
                    if ($ev->getFrom()->distanceSquared($ev->getTo()) < 20) {
                        break;
                    }
                    $ev->setCancelled();
                    break;
                }
            }
        }
    }

    public function onDropItem(PlayerDropItemEvent $ev) {
        foreach ($this->plugin->ins as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                if ($a->getPlayerMode($ev->getPlayer()->getName()) !== 0) {
                    $ev->setCancelled(true);
                    break;
                }
                if (!$this->plugin->cfg->get('allow_drop_item', true)) {
                    $ev->setCancelled(true);
                    break;
                }
                break;
            }
        }
    }

    public function onPickUp(InventoryPickupItemEvent $ev) {
        if (($p = $ev->getInventory()->getHolder()) instanceof Player) {
            foreach ($this->plugin->ins as $a) {
                if ($a->inArena($p->getName())) {
                    if ($a->getPlayerMode($p) !== 0):
                        
                        $ev->setCancelled();
                        break;
                    endif;
                }
            }
        }
    }

    public function onItemHeld(PlayerItemHeldEvent $ev) {
        foreach ($this->plugin->ins as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                foreach ($ev->getPlayer()->getName() as $p) {
                    if ($a->getPlayerMode($ev->getPlayer()->getName()) !== 0) {
                        if (!$this->plugin->cfg->getNested("item.enable_leave_item", false)) {
                            if (($ev->getItem()->getId() . ':' . $ev->getItem()->getDamage()) === $this->plugin->cfg->getNested("item.leave_item")) {
                                $now = microtime(true);
                                if ($this->plugin->cfg->getNested("enable_double_tap")) {
                                    if (!isset($this->tap[$player->getName()]) or $now - $this->tap[$player->getName()][1] >= 1.5 or $this->tap[$player->getName()][0] !== $loc) {
                                        $this->tap[$player->getName()] = [$loc, $now];
                                        $player->sendMessage($this->getMessage("tap-again", [$shop["itemName"], $shop["price"], $shop["amount"]]));
                                        return;
                                    } else {
                                        unset($this->tap[$player->getName()]);
                                    }
                                }
                            }
                            $ev->setCancelled();
                            $ev->getPlayer()->getInventory()->setHeldItemIndex(1);
                        }
                    }
                    break;
                }
            }
        }
    }

}
