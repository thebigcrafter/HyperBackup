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
use pocketmine\world\World;
use pocketmine\world\format\io\leveldb\LevelDB as LevelDBWorldProvider;
use RecursiveCallbackFilterIterator;
use function ltrim;
use function rtrim;
use function strlen;
use function substr;
use function dirname;
use function is_dir;
use function mkdir;
use function realpath;
use function rename;
use function rmdir;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function bin2hex;
use function random_bytes;
use function unlink;

class Archiver extends Task
{

	private string $inputFolder;
	private string $outputFilename;
	private string $password;
	/** @var World[] */
	private array $worlds;

	/**
	 * @param World[] $worlds
	 */
	public function __construct(string $inputFolder, string $outputPath, string $password, array $worlds = [])
	{
		$this->inputFolder = $inputFolder;
		$this->outputFilename = $outputPath;
		$this->password = $password;
		$this->worlds = $worlds;
	}

	public function onRun(): void
	{
		$temporaryFilename = null;
		$snapshotDirectory = null;
		$zip = null;

		try {
			$root = realpath($this->inputFolder);
			if($root === false) {
				throw new RuntimeException("Unable to resolve worlds directory: {$this->inputFolder}");
			}

			$temporaryPath = tempnam(dirname($this->outputFilename), "hbk");
			if($temporaryPath === false) {
				throw new RuntimeException("Unable to create a temporary backup archive beside {$this->outputFilename}");
			}
			$temporaryFilename = $temporaryPath;

			$levelDBDirectories = $this->snapshotLoadedLevelDBWorlds($root, $snapshotDirectory);

			$zip = new ZipArchive();
			if($zip->open($temporaryFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException("Unable to create temporary backup archive: {$temporaryFilename}");
			}
			$zip->setPassword($this->password);
			$files = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
					static function (SplFileInfo $file) use ($levelDBDirectories): bool {
						$path = realpath($file->getPathname());
						if($path === false) {
							return true;
						}
						foreach($levelDBDirectories as $databaseDirectory => $_) {
							if(self::isSameOrDescendantPath($path, $databaseDirectory)) {
								return false;
							}
						}
						return true;
					}
				),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach($files as $file) {
				if(!$file instanceof SplFileInfo) {
					continue;
				}
				$path = $file->getPathname();
				$relative = self::toZipPath(substr($path, strlen($root) + 1));
				if($file->isDir()) {
					$zip->addEmptyDir($relative);
					continue;
				}
				$this->addEncryptedFile($zip, $path, $relative);
			}

			foreach($levelDBDirectories as $snapshot) {
				$this->addSnapshotDatabase($zip, $snapshot["path"], $snapshot["archive_path"]);
			}

			if(!$zip->close()) {
				throw new RuntimeException("Unable to finalize temporary backup archive: {$temporaryFilename}");
			}
			$zip = null;

			if(!rename($temporaryFilename, $this->outputFilename)) {
				throw new RuntimeException("Unable to replace backup archive: {$this->outputFilename}");
			}
			$temporaryFilename = null;
		} catch(Throwable $e) {
			if($zip instanceof ZipArchive) {
				try {
					$zip->close();
				} catch(Throwable) {
				}
			}
			if($temporaryFilename !== null && is_file($temporaryFilename)) {
				unlink($temporaryFilename);
			}
			Server::getInstance()->getLogger()->error("HyperBackup could not create {$this->outputFilename}: {$e->getMessage()}");
		} finally {
			if($snapshotDirectory !== null) {
				$this->removeSnapshotDirectory($snapshotDirectory);
			}
		}
	}

	/**
	 * @return array<string, array{path: string, archive_path: string}> Source LevelDB directory to immutable snapshot location.
	 */
	private function snapshotLoadedLevelDBWorlds(string $root, ?string &$snapshotDirectory): array
	{
		$directories = [];
		$normalizedRoot = self::normalizePath($root);

		foreach($this->worlds as $world) {
			$provider = $world->getProvider();
			if(!$provider instanceof LevelDBWorldProvider) {
				continue;
			}

			$worldPath = realpath($root . DIRECTORY_SEPARATOR . $world->getFolderName());
			if($worldPath === false || !self::isSameOrDescendantPath($worldPath, $normalizedRoot) || self::normalizePath($worldPath) === $normalizedRoot) {
				continue;
			}

			if(!$world->save(true)) {
				throw new RuntimeException("Unable to save loaded world before backup: {$world->getFolderName()}");
			}

			$sourceDatabase = realpath($worldPath . DIRECTORY_SEPARATOR . "db");
			if($sourceDatabase === false || !is_dir($sourceDatabase)) {
				throw new RuntimeException("Unable to resolve LevelDB directory for loaded world: {$world->getFolderName()}");
			}

			if($snapshotDirectory === null) {
				$snapshotDirectory = $this->createSnapshotDirectory();
			}

			$relativeWorldPath = substr($worldPath, strlen($root) + 1);
			$snapshotDatabase = $snapshotDirectory . DIRECTORY_SEPARATOR . $relativeWorldPath . DIRECTORY_SEPARATOR . "db";
			$snapshotParent = dirname($snapshotDatabase);
			if(!is_dir($snapshotParent) && !mkdir($snapshotParent, 0700, true) && !is_dir($snapshotParent)) {
				throw new RuntimeException("Unable to create LevelDB snapshot directory: {$snapshotParent}");
			}

			LevelDBSnapshotExporter::export($provider->getDatabase(), $snapshotDatabase);
			$directories[self::normalizePath($sourceDatabase)] = [
				"path" => $snapshotDatabase,
				"archive_path" => self::toZipPath($relativeWorldPath . DIRECTORY_SEPARATOR . "db")
			];
		}

		return $directories;
	}

	private function createSnapshotDirectory(): string
	{
		$parent = dirname($this->outputFilename);
		for($attempt = 0; $attempt < 5; ++$attempt) {
			$directory = $parent . DIRECTORY_SEPARATOR . "hyperbackup-leveldb-" . bin2hex(random_bytes(12));
			if(mkdir($directory, 0700)) {
				return $directory;
			}
		}
		throw new RuntimeException("Unable to create a unique LevelDB snapshot directory beside {$this->outputFilename}");
	}

	private function addSnapshotDatabase(ZipArchive $zip, string $snapshotDatabase, string $archivePath): void
	{
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($snapshotDatabase, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach($files as $file) {
			if(!$file instanceof SplFileInfo || !$file->isFile() || $file->getFilename() === "LOCK") {
				continue;
			}
			$path = $file->getPathname();
			$relativeFile = self::toZipPath(substr($path, strlen($snapshotDatabase) + 1));
			$this->addEncryptedFile($zip, $path, $archivePath . "/" . $relativeFile);
		}
	}

	private function addEncryptedFile(ZipArchive $zip, string $path, string $relative): void
	{
		if(!$zip->addFile($path, $relative)) {
			throw new RuntimeException("Unable to add backup file: {$path}");
		}
		if(!$zip->setEncryptionName($relative, ZipArchive::EM_AES_256)) {
			throw new RuntimeException("Unable to encrypt backup file: {$relative}");
		}
	}

	private function removeSnapshotDirectory(string $directory): void
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach($iterator as $entry) {
			if(!$entry instanceof SplFileInfo) {
				continue;
			}
			$path = $entry->getPathname();
			if($entry->isLink() || !$entry->isDir()) {
				unlink($path);
			} else {
				rmdir($path);
			}
		}
		rmdir($directory);
	}

	private static function isSameOrDescendantPath(string $path, string $directory): bool
	{
		$path = self::normalizePath($path);
		$directory = self::normalizePath($directory);
		if(DIRECTORY_SEPARATOR === "\\") {
			$path = strtolower($path);
			$directory = strtolower($directory);
		}
		return $path === $directory || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
	}

	private static function normalizePath(string $path): string
	{
		return rtrim(str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
	}

	private static function toZipPath(string $path): string
	{
		return str_replace(DIRECTORY_SEPARATOR, "/", ltrim($path, DIRECTORY_SEPARATOR));
	}
}
