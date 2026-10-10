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

The backup task reads world files while the server is running. For a consistent backup, use a maintenance window and ensure that the relevant worlds are not being written while the archive is created. On Windows, an open LevelDB file can prevent the ZIP archive from being finalized; a backup failure is logged rather than reported as a successful archive.

The current implementation has no restore workflow. Verify the archive separately before treating it as a recovery copy, and preserve the original server data until a restoration procedure has been tested.

## Build and test

Install the locked development dependencies and run the available checks:

```sh
composer install
composer analyse
composer run-script build
```

The build workflow uses [DevTools](https://github.com/NhanAZ/DevTools) to package and validate the plugin PHAR. The workflow pins the builder revision used by CI; this README names the tool without copying the CI pin into user-facing documentation.

Before a release, inspect the PHAR manifest and test the plugin in an isolated PocketMine-MP server data directory. Do not run backup tests against a live production world or database.

## Support and contributing

For a reproducible issue, open an [issue](https://github.com/thebigcrafter/HyperBackup/issues) with the plugin version, PocketMine-MP version, PHP/extension versions, command used and relevant log output.

Community discussion is available through the [thebigcrafter Discord server](https://discord.gg/pdUvA8nXJC). Preserve the existing project attribution and GPL-3.0-only license when contributing.

## License

HyperBackup is licensed under [GPL-3.0-only](LICENSE).
