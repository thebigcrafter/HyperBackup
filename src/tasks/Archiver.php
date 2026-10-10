<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\tasks;

use pocketmine\scheduler\Task;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use SplFileInfo;
use ZipArchive;
use FilesystemIterator;
use pocketmine\Server;
use function ltrim;
use function rtrim;
use function strlen;
use function substr;
use function dirname;
use function is_file;
use function rename;
use function tempnam;
use function unlink;

class Archiver extends Task
{

	private string $inputFolder;
	private string $outputFilename;
	private string $password;

	public function __construct(string $inputFolder, string $outputPath, string $password)
	{
		$this->inputFolder = $inputFolder;
		$this->outputFilename = $outputPath;
		$this->password = $password;
	}

	public function onRun(): void
	{
		$temporaryFilename = null;
		$zip = null;

		try {
			$temporaryPath = tempnam(dirname($this->outputFilename), "hbk");
			if($temporaryPath === false) {
				throw new RuntimeException("Unable to create a temporary backup archive beside {$this->outputFilename}");
			}
			$temporaryFilename = $temporaryPath;

			$zip = new ZipArchive();
			if($zip->open($temporaryFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException("Unable to create temporary backup archive: {$temporaryFilename}");
			}
			$zip->setPassword($this->password);
			$root = rtrim($this->inputFolder, DIRECTORY_SEPARATOR);
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach($files as $file) {
				if(!$file instanceof SplFileInfo) {
					continue;
				}
				$path = $file->getPathname();
				$relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
				if($file->isDir()) {
					$zip->addEmptyDir($relative);
					continue;
				}
				if(!$zip->addFile($path, $relative)) {
					throw new RuntimeException("Unable to add backup file: {$path}");
				}
				if(!$zip->setEncryptionName($relative, ZipArchive::EM_AES_256)) {
					throw new RuntimeException("Unable to encrypt backup file: {$relative}");
				}
			}
			if(!$zip->close()) {
				throw new RuntimeException("Unable to finalize temporary backup archive: {$temporaryFilename}");
			}
			$zip = null;

			if(!rename($temporaryFilename, $this->outputFilename)) {
				throw new RuntimeException("Unable to replace backup archive: {$this->outputFilename}");
			}
			$temporaryFilename = null;
		} catch(Throwable $e) {
			if($zip instanceof ZipArchive) {
				$zip->close();
			}
			if($temporaryFilename !== null && is_file($temporaryFilename)) {
				unlink($temporaryFilename);
			}
			Server::getInstance()->getLogger()->error("HyperBackup could not create {$this->outputFilename}: {$e->getMessage()}");
		}
	}
}
