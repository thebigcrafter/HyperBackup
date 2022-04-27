<?php

namespace thebigcrafter\HyperBackup\utils;

use thebigcrafter\HyperBackup\HyperBackup;

trait SingletonPattern
{
	private static HyperBackup $instance;

	public static function getInstance(): HyperBackup
	{
		return self::$instance;
	}

	/**
	 * @param HyperBackup $instance
	 *
	 * @return void
	 */
	public static function setInstance(HyperBackup $instance): void
	{
		self::$instance = $instance;
	}
}