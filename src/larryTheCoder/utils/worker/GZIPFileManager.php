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

namespace larryTheCoder\utils\worker;

use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;

class GZIPFileManager {
	// Well, if you take a look of libasyncsql virion and
	// this class, you may see some familiarity within these codes.

	/** @var GZIPFilesThread */
	public $taskWorker;
	/** @var callable[] */
	private $handlers;

	/** @var SleeperNotifier */
	private $notifier;
	/**@var GZIPQueue */
	private $revQueue;
	/**@var GZIPQueueCompletion */
	private $resQueue;

	public function __construct(){
		$notifier = new SleeperNotifier();
		Server::getInstance()->getTickSleeper()->addNotifier($notifier, function(): void{
			$this->handleNotifications();
		});

		$this->revQueue = new GZIPQueue();
		$this->resQueue = new GZIPQueueCompletion();

		$this->taskWorker = new GZIPFilesThread($notifier, $this->revQueue, $this->resQueue);
		$this->taskWorker->start(PTHREADS_INHERIT_INI | PTHREADS_INHERIT_CONSTANTS);

		$this->revQueue->scheduleDecompression(0, "E:\ACD-HyruleServer\PostNotif\OpCache.zip", "E:\ACD-HyruleServer\PostNotif\players");
	}

	private function handleNotifications(){
		$this->resQueue->fetchResult($resId);
		var_dump($resId);
	}
}