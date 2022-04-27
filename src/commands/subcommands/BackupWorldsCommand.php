<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\commands\subcommands;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use thebigcrafter\HyperBackup\HyperBackup;
use thebigcrafter\HyperBackup\tasks\Archiver;

class BackupWorldsCommand extends BaseSubCommand
{

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
	{
		$dataPath = realpath(HyperBackup::getInstance()->getServer()->getDataPath()) . "/";
		$worldsBackupFile = $dataPath . "backups/worlds";

		$start = microtime(true);

		HyperBackup::getInstance()->getScheduler()->scheduleTask(new Archiver($dataPath . "worlds", $dataPath . $worldsBackupFile));

		$sender->sendMessage("Archive created successfully in " . round(microtime(true) - $start, 4) . " seconds");

		$data = base64_encode(file_get_contents($worldsBackupFile));

		echo $data;
		// TODO: Upload it to mysql db
	}

	protected function prepare(): void
	{
	}
}