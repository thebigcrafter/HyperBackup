[![PHPStan](https://github.com/thebigcrafter/HyperBackup/actions/workflows/phpstan-analysis.yml/badge.svg)](https://github.com/thebigcrafter/HyperBackup/actions/workflows/phpstan-analysis.yml)
[![GitHub](https://img.shields.io/github/license/thebigcrafter/HyperBackup)](https://github.com/thebigcrafter/HyperBackup/blob/main/LICENSE)
[![Discord](https://img.shields.io/badge/chat-on%20discord-blue)](https://discord.gg/pdUvA8nXJC)

# HyperBackup

HyperBackup is a PocketMine-MP API 5 plugin that creates a password-protected ZIP archive of the server's `worlds` directory.

## Requirements

- PocketMine-MP API `5.0.0` or newer.
- PHP 8.1 or newer.
- The PHP `zip` extension, required by `ZipArchive`.
- The `hyperbackup.cmd` permission to run the command.

The plugin currently archives worlds only. It does not back up plugins, configuration files, databases or `plugin_data`, and it does not provide a restore command.

## Installation

1. Download a compatible `HyperBackup.phar` from a release when one is available, or build it locally.
2. Copy the PHAR into the server's `plugins/` directory.
3. Start the server and confirm that HyperBackup enables without errors.
4. Schedule a world backup with `/backup worlds <password>`.

The shorter `/backup w <password>` alias is also available. If no password is supplied, the current implementation uses the default `HyperBackup`; always provide an explicit password for a real backup.

The archive is written to `backups/worlds.zip` under the PocketMine-MP server data directory and is overwritten on each scheduled backup.

## Safe backup operation

The backup task runs on the server thread, so large worlds can pause normal ticks while they are archived. Loaded worlds are saved first. For each loaded LevelDB world, HyperBackup exports a consistent LevelDB snapshot and archives that snapshot instead of reading the database files held open by the server. Other world files are read from disk during the same task; avoid changing them from outside the server until the backup finishes.

The plugin does not provide an in-game restore command. The manual procedure below was tested with an isolated server and the PHP `ZipArchive` extension used to create the archive.

## Manual restore

1. Stop the server completely and keep an untouched copy of `backups/worlds.zip`. The next backup replaces that path.
2. Enter the backup password and extract the archive into a new, empty staging directory with PHP `ZipArchive` or another tool that supports AES-256-encrypted ZIP entries. The archive contains each world as `<world-folder>/...`; extracting into `<staging-directory>` creates one directory per backed-up world.
3. Keep the current `<server-data>/worlds` directory intact by moving it outside the active `worlds` path, for example to `worlds-before-restore`. Move the extracted staging directory into `<server-data>/worlds`; do not merge it into an active world directory.
4. Start the server and confirm the expected worlds load without errors. Keep the original archive and the moved-aside world data until the restored worlds have been checked.

This archive contains world data only. It does not restore plugins, configuration, databases outside the world directories, or `plugin_data`.

## Build and test

Install the locked development dependencies and run the available checks:

```sh
composer install
composer analyse
```

The build workflow uses [DevTools](https://github.com/NhanAZ/DevTools) to package and validate the plugin PHAR. The workflow pins the builder revision used by CI; this README names the tool without copying the CI pin into user-facing documentation.

Before a release, inspect the PHAR manifest and test the plugin in an isolated PocketMine-MP server data directory. Do not run backup tests against a live production world or database.

## Support and contributing

For a reproducible issue, open an [issue](https://github.com/thebigcrafter/HyperBackup/issues) with the plugin version, PocketMine-MP version, PHP/extension versions, command used and relevant log output.

Community discussion is available through the [thebigcrafter Discord server](https://discord.gg/pdUvA8nXJC). Preserve the existing project attribution and GPL-3.0-only license when contributing.

## License

HyperBackup is licensed under [GPL-3.0-only](LICENSE).
