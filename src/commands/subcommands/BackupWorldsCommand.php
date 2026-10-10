<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\commands\subcommands;

use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\args\RawStringArgument;
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
		$worldsBackupFile = $dataPath . "backups/worlds.zip";

		$start = microtime(true);
		
		$password = $args["password"] ?? "HyperBackup";

		HyperBackup::getInstance()->getScheduler()->scheduleTask(new Archiver($dataPath . "worlds", $worldsBackupFile, $password));

		$sender->sendMessage("World backup scheduled in " . round(microtime(true) - $start, 4) . " seconds");
	}

	protected function prepare(): void
	{
		$this->registerArgument(0, new RawStringArgument("password", optional: true));
	}
}
