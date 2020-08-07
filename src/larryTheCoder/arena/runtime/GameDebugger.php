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

use pocketmine\Thread;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class GameDebugger extends Thread {

	/** @var bool */
	protected $shutdown = false;
	/** @var string */
	private $logFile;
	/** @var \Threaded */
	private $logStream;
	/** @var \DateTime */
	private $dateTime;

	public function __construct(string $logFile, \DateTime $time){
		$this->dateTime = $time;

		touch($logFile);
		$this->logFile = $logFile;

		$this->logStream = new \Threaded;

		$this->start(PTHREADS_INHERIT_NONE);

		$this->log("----- INITIALIZATION -----");
	}

	public function log(string $message){
		$time = $this->dateTime;
		$time->setTimestamp(time());

		$this->synchronized(function() use ($message, $time) : void{
			$this->logStream[] = "[" . $time->format("Y-m-d") . " " . $time->format("H:i:s") . "]: " . TextFormat::clean($message) . PHP_EOL;
		});
	}

	public function quit(){
		$this->log("----- SHUTDOWN -----\n");

		$this->shutdown = true;
		parent::quit();
	}

	public function run(){
		$this->registerClassLoader();

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

	/**
	 * @param \Throwable $e
	 * @param mixed[][]|null $trace
	 * @return void
	 *
	 * @phpstan-param list<array<string, mixed>>|null $trace
	 */
	public function logException(\Throwable $e, $trace = null){
		if($trace === null){
			$trace = $e->getTrace();
		}

		$this->synchronized(function() use ($e, $trace) : void{
			$this->log("-----");
			$this->log(self::printExceptionMessage($e));
			foreach(Utils::printableTrace($trace) as $line){
				$this->log($line);
			}
			for($prev = $e->getPrevious(); $prev !== null; $prev = $prev->getPrevious()){
				$this->log("Previous: " . self::printExceptionMessage($prev));
				foreach(Utils::printableTrace($prev->getTrace()) as $line){
					$this->log("  " . $line);
				}
			}
			$this->log("-----");
		});
	}

	private static function printExceptionMessage(\Throwable $e): string{
		static $errorConversion = [
			0                   => "EXCEPTION",
			E_ERROR             => "E_ERROR",
			E_WARNING           => "E_WARNING",
			E_PARSE             => "E_PARSE",
			E_NOTICE            => "E_NOTICE",
			E_CORE_ERROR        => "E_CORE_ERROR",
			E_CORE_WARNING      => "E_CORE_WARNING",
			E_COMPILE_ERROR     => "E_COMPILE_ERROR",
			E_COMPILE_WARNING   => "E_COMPILE_WARNING",
			E_USER_ERROR        => "E_USER_ERROR",
			E_USER_WARNING      => "E_USER_WARNING",
			E_USER_NOTICE       => "E_USER_NOTICE",
			E_STRICT            => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED        => "E_DEPRECATED",
			E_USER_DEPRECATED   => "E_USER_DEPRECATED",
		];

		$errstr = preg_replace('/\s+/', ' ', trim($e->getMessage()));

		$errno = $e->getCode();
		$errno = $errorConversion[$errno] ?? $errno;

		$errfile = Utils::cleanPath($e->getFile());
		$errline = $e->getLine();

		return get_class($e) . ": \"$errstr\" ($errno) in \"$errfile\" at line $errline";
	}
}
