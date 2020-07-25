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

use pocketmine\snooze\SleeperNotifier;
use pocketmine\Thread;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Provide an asynchronous compression/decompression for worlds.
 * It is used to cut down the amount of time needed to compress these files
 * on main thread.
 *
 * @package larryTheCoder\utils\worker
 */
class GZIPFilesThread extends Thread {

	/** @var SleeperNotifier */
	private $notifier;

	/** @var GZIPQueue */
	private $queue;
	/** @var GZIPQueueCompletion */
	private $completion;

	public function __construct(SleeperNotifier $notifier, GZIPQueue $queue, GZIPQueueCompletion $completion){
		$this->notifier = $notifier;

		$this->queue = $queue;
		$this->completion = $completion;
	}

	// Ground breaking discovery...
	public function run(){
		while(true){
			$row = $this->queue->fetchQuery();
			if(!is_string($row)){
				break;
			}

			[$queryId, $fromPath, $toPath, $compress] = unserialize($row);
			if($compress){
				// "folder" "target.zip"
				$this->compressFile($fromPath, $toPath); // Overwrites the whole zip file.
			}else{
				// "target.zip" "folder"
				$this->decompressFile($fromPath, $toPath); // Overwrite the whole folder path.
			}

			$this->completion->publishResult($queryId);
			$this->notifier->wakeupSleeper();
		}
	}

	function compressFile(string $source, string $toPath){
		// Get real path for our folder
		$rootPath = realpath($source);

		// Initialize archive object
		$zip = new ZipArchive();
		$zip->open($toPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		// Create recursive directory iterator
		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($rootPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach($files as $name => $file){
			// Skip directories (they would be added automatically)
			if(!$file->isDir()){
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($rootPath) + 1);

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
				$zip->setCompressionName($filePath, ZipArchive::CM_BZIP2);
			}
		}

		// Zip archive will be created only after closing object
		$zip->close();
	}

	function decompressFile($fromPath, $toPath){
		// get the absolute path to $file
		$zip = new ZipArchive;
		$res = $zip->open($fromPath);

		if(!$res) return;

		$zip->extractTo($toPath);
		$zip->close();

	}

	public function quit(){
		$this->stopRunning();
	}

	public function stopRunning(): void{
		$this->queue->invalidate();
	}
}