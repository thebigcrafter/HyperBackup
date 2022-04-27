<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\commands\subcommands;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use thebigcrafter\HyperBackup\HyperBackup;
use thebigcrafter\HyperBackup\tasks\Archiver;

class BackupWorldsCommand extends BaseSubCommand
{

	/**
	 * @param CommandSender $sender
	 * @param string $aliasUsed
	 * @param array<string> $args
	 *
	 * @return void
	 */

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$dataPath = realpath(HyperBackup::getInstance()->getServer()->getDataPath()) . "/";
		$worldsBackupFile = $dataPath . "backups/worlds";

		$start = microtime(true);

		HyperBackup::getInstance()->getScheduler()->scheduleTask(new Archiver($dataPath . "worlds", $dataPath . $worldsBackupFile));

		$sender->sendMessage("Archive created successfully in " . round(microtime(true) - $start, 4) . " seconds");

		$fileContents = file_get_contents($worldsBackupFile);

		if($fileContents === false) {
			$sender->sendMessage("Something went wrong while creating the archive");
			return;
		}

		$data = base64_encode($fileContents);

		echo $data;
		// TODO: Upload it to mysql db
	}

	protected function prepare(): void
	{
	}
}