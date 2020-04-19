<?php
/**
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

namespace larryTheCoder\arena\runtime;

use pocketmine\utils\TextFormat;

class GameDebugger extends \Thread {

	/** @var bool */
	protected $shutdown = false;
	/** @var string */
	private $logFile;
	/** @var \Threaded */
	private $logStream;
	/** @var \DateTime */
	private $dateTime;

	public function __construct(string $logFile, \DateTime $time){
		try{
			$this->dateTime = $time;

			touch($logFile);
			$this->logFile = $logFile;

			$this->logStream = new \Threaded;

			$this->start(PTHREADS_INHERIT_NONE);
		}catch(\Exception $e){
		}

		$this->log("----- INITIALIZATION -----");
	}

	public function log(string $message){
		$time = $this->dateTime;
		$time->setTimestamp(time());

		$this->synchronized(function() use ($message, $time) : void{
			$this->logStream[] = "[" . $time->format("Y-m-d") . " " . $time->format("H:i:s") . "]: " . TextFormat::clean($message) . PHP_EOL;
		});
	}

	public function shutdown(){
		$this->log("----- SHUTDOWN -----");

		$this->shutdown = true;
	}

	public function run(){
		$logResource = fopen($this->logFile, "ab");
		if(!is_resource($logResource)){
			throw new \RuntimeException("Couldn't open log file");
		}

		while(!$this->shutdown){
			$this->writeLogStream($logResource);
			$this->synchronized(function(){
				$this->wait(25000);
			});
		}

		$this->writeLogStream($logResource);

		fclose($logResource);
	}

	/**
	 * @param resource $logResource
	 */
	private function writeLogStream($logResource){
		while($this->logStream->count() > 0){
			$chunk = $this->logStream->shift();
			fwrite($logResource, $chunk);
		}
	}
}
