<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup;

require_once __DIR__ . '/../vendor/autoload.php';

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use thebigcrafter\HyperBackup\commands\BackupCommand;
use thebigcrafter\HyperBackup\utils\SingletonPattern;

class HyperBackup extends PluginBase {

	use SingletonPattern;

	public static string $PREFIX = TextFormat::WHITE . "[" . TextFormat::BLUE . "HyperBackup" . TextFormat::WHITE . "] " . TextFormat::RESET;

	public Config $config;

	/** @return void */
	protected function onEnable(): void
	{
		self::setInstance($this);

		$this->setupDataFolder();
		$this->registerCommands();
	}

	/** @return void */
	private function registerCommands(): void {
		$commands = [
			new BackupCommand($this, "backup", "Backup command")
		];

		$this->getServer()->getCommandMap()->registerAll("hyperbackup", $commands);
	}

	/** @return void */
	private function setupDataFolder(): void {
		if(!is_dir($this->getServer()->getDataPath() . "backups")) {
			mkdir($this->getServer()->getDataPath() . "backups");
		}

		$this->saveDefaultConfig();

		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
	}
}