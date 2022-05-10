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
	private string $password;

	public function __construct(string $inputFolder, string $outputPath, string $password)
	{
		$this->inputFolder = $inputFolder;
		$this->outputFilename = $outputPath;
		$this->password = $password
	}

	public function onRun(): void
	{
		$zip = new ZipFile();

		try {
			$zip->addDir($this->inputFolder);
			$zip->saveAsFile($this->outputFilename);
			$zip->setPassword($this->password);
			$zip->close();
		} catch (ZipException $e) {
			echo $e->getMessage();
		}
	}
}
