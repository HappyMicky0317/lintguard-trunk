<?php
declare(strict_types=1);

namespace LintGuard;

use LintGuard\CacheObject;

interface CacheInterface {
	public function load(): CacheObject;

	public function save(CacheObject $cacheObject): void;
}
