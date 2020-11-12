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

namespace larryTheCoder\arena\api\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Using a thread to compress files is dumb, they do not often being used
 * for heavy tasks and it will be a waste of resources.
 */
class CompressionAsyncTask extends AsyncTask {

	/** @var string */
	private $data;

	/**
	 * CompressionAsyncTask constructor.
	 * @param array<int, string|bool> $data
	 * @param callable $result
	 */
	public function __construct(array $data, callable $result){
		$this->data = serialize($data);

		$this->storeLocal($result);
	}

	public function onRun(){
		[$fromPath, $toPath, $compress] = $data = unserialize($this->data);

		if($compress){
			// "folder" "target.zip"
			self::compressFile($fromPath, $toPath); // Overwrites the whole zip file.
		}else{
			// "target.zip" "folder"
			self::decompressFile($fromPath, $toPath); // Overwrite the whole folder path.
		}
	}

	public static function compressFile(string $source, string $toPath): void{
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

	public static function decompressFile(string $fromPath, string $toPath): void{
		// get the absolute path to $file
		$zip = new ZipArchive;
		$res = $zip->open($fromPath);

		if(!$res) return;

		// Force deleting the same arena name.
		AsyncDirectoryDelete::deleteDirectory($toPath);

		$zip->extractTo($toPath);
		$zip->close();
	}

	public function onCompletion(Server $server): void{
		$call = $this->fetchLocal();
		$call();
	}
}