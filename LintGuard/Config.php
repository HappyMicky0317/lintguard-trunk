<?php
declare(strict_types=1);

namespace LintGuard;

use LintGuard\LinterOptions;

class Config {
	/**
	 * @var string
	 */
	public $svn = 'svn';

	/**
	 * @var string
	 */
	public $git = 'git';

	/**
	 * @var Record<string, LinterOptions>
	 */
	public $linters = [];

	public function __construct() {
		$this->linters = [
			'phpcs' => new LinterOptions('phpcs', ['--report=json']),
		];
	}

	public static function fromJson(string $json): self {
		$raw = json_decode($json, null, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR );
		$config = new self();
		if (! empty($raw['version-control']['svn'])) {
			$config->svn = $raw['version-control']['svn'];
		}
		if (! empty($raw['version-control']['git'])) {
			$config->git = $raw['version-control']['git'];
		}
		if (! empty($raw['linter-options']) && is_array($raw['linter-options'])) {
			foreach ($raw['linter-options'] as $key => $linter) {
				if (! isset($config->linters[$key])) {
					$config->linters[$key] = new LinterOptions();
				}
				if (! empty($raw['linter-options'][$key]['command'])) {
					$config->linters[$key]->command = $raw['linter-options'][$key]['command'];
				}
				if (! empty($raw['linter-options'][$key]['args'])) {
					$config->linters[$key]->command = $raw['linter-options'][$key]['args'];
				}
			}
		}
		return $config;
	}
}
