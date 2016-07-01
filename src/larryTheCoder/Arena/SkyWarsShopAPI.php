<?php

namespace larryTheCoder\Utils;


use larryTheCoder\SkyWarsAPI;
/**
 * SkyWarsShopAPI: SkyWars shopping tools
 *
 * @author larryTheHarry
 */
class SkyWarsShopAPI {

    public $plugin;
    public function __construct(SkyWarsAPI $plugin) {
        $this->plugin = $plugin;
    }

    
}
