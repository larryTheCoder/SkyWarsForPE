<?php
/*
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2020 larryTheCoder and contributors
 *
 * Permission is hereby granted to any persons and/or organizations
 * using this software to copy, modify, merge, publish, and distribute it.
 * Said persons and/or organizations are not allowed to use the software or
 * any derivatives of the work for commercial use or any other means to generate
 * income, nor are they allowed to claim this software as their own.
 *
 * The persons and/or organizations are also disallowed from sub-licensing
 * and/or trademarking this software without explicit permission from larryTheCoder.
 *
 * Any persons and/or organizations using this software must disclose their
 * source code and have it publicly available, include this license,
 * provide sufficient credit to the original authors of the project (IE: larryTheCoder),
 * as well as provide a link to the original project.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
 * USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

declare(strict_types = 1);

namespace larryTheCoder\worker;

use larryTheCoder\SkyWarsPE;
use pocketmine\scheduler\AsyncPool;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\GarbageCollectionTask;
use pocketmine\Server;
use pocketmine\utils\MainLogger;

// According to dylan, never perform IO operations in PMMP async pool,
// since it will lag the server if the server enables packet compression.
class LevelAsyncPool extends AsyncPool {

	/** @var LevelAsyncPool */
	private static $instance;

	public function __construct(SkyWarsPE $plugin, int $workerSize){
		parent::__construct(Server::getInstance(), $workerSize, 255, Server::getInstance()->getLoader(), MainLogger::getLogger());

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			if(($w = $this->shutdownUnusedWorkers()) > 0){
				MainLogger::getLogger()->debug("Shut down $w idle async pool workers");
			}
			foreach($this->getRunningWorkers() as $i){
				$this->submitTaskToWorker(new GarbageCollectionTask(), $i);
			}
		}), 30 * 60 * 20);

		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick): void{
			$this->collectTasks();
		}), 1);

		self::$instance = $this;
	}

	public static function getAsyncPool(): LevelAsyncPool{
		return self::$instance;
	}
}