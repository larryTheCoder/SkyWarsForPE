<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace larryTheCoder\Utils;

use larryTheCoder\SkyWarsAPI;
use larryTheCoder\Arena\Arena;
/**
 * Description of SkyWarsListener
 *
 * @author Windows 7
 */
class SkyWarsListener {
    
    public function __construct(SkyWarsAPI $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @staticvar Singleton $instance The *Singleton* instances of this class.
     *           
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance(SkyWarsAPI $plugin) {
        static $instance = null;
        if (null === $instance) {
            $instance = new SkyWarsListener($plugin);
        }
        return $instance;
    }
    
    /** 
     * AnnounceGameFinish = arena finish broadcast event
     * 
     * @param Arena $arena
     */
    public static function AnnounceGameFinish(Arena $arena) {
        /** @todo MORE MORE MORE */
    }
}
