<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\tasks;

use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use pocketmine\scheduler\Task;

class Archiver extends Task
{

	private string $inputFolder;
	private string $outputFilename;

	public function __construct(string $inputFolder, string $outputPath)
	{
		$this->inputFolder = $inputFolder;
		$this->outputFilename = $outputPath;
	}

	public function onRun(): void
	{
		$zip = new ZipFile();

		try {
			$zip->addDir($this->inputFolder);
			$zip->saveAsFile($this->outputFilename);
			$zip->setPassword("HyperBackup");
			$zip->close();
		} catch (ZipException $e) {
			echo $e->getMessage();
		}
	}
}