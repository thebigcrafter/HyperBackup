<?php

declare(strict_types=1);

namespace thebigcrafter\HyperBackup\tasks;

use RuntimeException;

/**
 * Exports a consistent LevelDB view to a separate, ordinary database directory.
 */
final class LevelDBSnapshotExporter{
	private const BATCH_SIZE = 256;

	public static function export(\LevelDB $source, string $destination): void{
		if(file_exists($destination)){
			throw new RuntimeException("LevelDB snapshot destination already exists: {$destination}");
		}

		$snapshot = $source->getSnapshot();
		$iterator = null;
		$target = null;
		$batch = null;

		try{
			$iterator = $source->getIterator([
				"snapshot" => $snapshot,
				"verify_check_sum" => true,
				"fill_cache" => false
			]);
			$target = new \LevelDB($destination, [
				"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
				"block_size" => 64 * 1024
			]);
			$batch = new \LevelDBWriteBatch();
			$batchSize = 0;

			foreach($iterator as $key => $value){
				if(!is_string($key) || !is_string($value)){
					throw new RuntimeException("LevelDB snapshot returned a non-string key or value");
				}
				$batch->put($key, $value);
				++$batchSize;

				if($batchSize >= self::BATCH_SIZE){
					self::writeBatch($target, $batch);
					$batch->clear();
					$batchSize = 0;
				}
			}

			$error = $iterator->getError();
			if($error !== false && $error !== null && $error !== ""){
				if(!is_string($error)){
					throw new RuntimeException("Unable to read LevelDB snapshot because the iterator returned an invalid error value");
				}
				throw new RuntimeException("Unable to read LevelDB snapshot: {$error}");
			}

			if($batchSize > 0){
				self::writeBatch($target, $batch);
			}

		} finally{
			if($iterator instanceof \LevelDBIterator){
				$iterator->destroy();
			}
			unset($iterator);
			if($batch instanceof \LevelDBWriteBatch){
				$batch->clear();
			}
			if($target instanceof \LevelDB){
				// The extension normally closes databases during destruction, but that can leave
				// Windows file handles alive while the archive queues the snapshot files.
				// close() is deprecated by the extension because destruction is preferred, so
				// suppress only that compatibility warning after explicitly releasing the handle.
				@$target->close();
			}
			unset($batch, $target);
			$snapshot->release();
			unset($snapshot);
		}
	}

	private static function writeBatch(\LevelDB $target, \LevelDBWriteBatch $batch): void{
		if(!$target->write($batch)){
			throw new RuntimeException("Unable to write a LevelDB snapshot batch");
		}
	}
}
