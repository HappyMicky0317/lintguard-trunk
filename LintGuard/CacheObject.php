<?php
declare(strict_types=1);

namespace LintGuard;

class CacheObject {
	/**
	 * @var CacheEntry[]
	 */
	public $entries = [];

	/**
	 * @var string
	 */
	public $cacheVersion;
}

