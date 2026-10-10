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
		try {
			$zip = new ZipArchive();
			if($zip->open($this->outputFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException("Unable to create backup archive: {$this->outputFilename}");
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
					$zip->close();
					throw new RuntimeException("Unable to add backup file: {$path}");
				}
				if(!$zip->setEncryptionName($relative, ZipArchive::EM_AES_256)) {
					$zip->close();
					throw new RuntimeException("Unable to encrypt backup file: {$relative}");
				}
			}
			if(!$zip->close()) {
				throw new RuntimeException("Unable to finalize backup archive: {$this->outputFilename}");
			}
		} catch(Throwable $e) {
			Server::getInstance()->getLogger()->error("HyperBackup could not create {$this->outputFilename}: {$e->getMessage()}");
		}
	}
}
