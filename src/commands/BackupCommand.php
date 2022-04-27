<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\commands;

use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use thebigcrafter\HyperBackup\commands\subcommands\BackupWorldsCommand;

class BackupCommand extends BaseCommand
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
		$this->sendUsage();
	}

	protected function prepare(): void
	{
		$this->registerSubCommand(new BackupWorldsCommand("worlds", "Backup worlds", ["w"]));
	}
}