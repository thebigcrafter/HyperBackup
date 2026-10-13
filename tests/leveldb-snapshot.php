<?php

declare(strict_types=1);

use thebigcrafter\HyperBackup\tasks\LevelDBSnapshotExporter;

require __DIR__ . "/../src/tasks/LevelDBSnapshotExporter.php";

function expectSnapshot(bool $condition, string $message): void{
	if(!$condition){
		throw new RuntimeException($message);
	}
}

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "hyperbackup-leveldb-snapshot-test-" . bin2hex(random_bytes(8));
if(!mkdir($testDirectory)){
	throw new RuntimeException("Unable to create LevelDB snapshot test directory");
}

$source = null;
$copy = null;
try{
	$source = new LevelDB($testDirectory . DIRECTORY_SEPARATOR . "source", [
		"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
		"block_size" => 64 * 1024
	]);
	expectSnapshot($source->put("included", "snapshot-value") !== false, "Unable to seed source database");
	expectSnapshot($source->put("binary-\0-key", "binary-value") !== false, "Unable to seed binary key");

	$destination = $testDirectory . DIRECTORY_SEPARATOR . "exported";
	LevelDBSnapshotExporter::export($source, $destination);
	$source->put("added-after-export", "later-value");

	$copy = new LevelDB($destination, [
		"compression" => LEVELDB_ZLIB_RAW_COMPRESSION,
		"block_size" => 64 * 1024
	]);
	expectSnapshot($copy->get("included") === "snapshot-value", "Exported snapshot is missing a value");
	expectSnapshot($copy->get("binary-\0-key") === "binary-value", "Exported snapshot changed a binary key");
	expectSnapshot($copy->get("added-after-export") === false, "Exported snapshot included a later write");
	unset($copy, $source);
	$copy = null;
	$source = null;

	echo "PASS: LevelDB snapshot export preserves key/value data and excludes later writes\n";
} finally{
	if($copy instanceof LevelDB){
		unset($copy);
	}
	if($source instanceof LevelDB){
		unset($source);
	}
	unset($copy, $source);
}
