<?php
declare(strict_types=1);

namespace LintGuard;

class CliOptions {
	/**
	 * @var string
	 */
	public $configPath = '.lintguardrc.json';

	/**
	 * @var LintGuard\Config
	 */
	public $config;

	/**
	 * @var string
	 */
	public $linter;

	/**
	 * @var bool
	 */
	public $svnMode = false;

	/**
	 * @var bool
	 */
	public $gitUnstaged = false;

	/**
	 * @var bool
	 */
	public $gitStaged = false;

	/**
	 * @var string
	 */
	public $gitBase = '';

	/**
	 * @var string
	 */
	public $reporter = 'human';

	/**
	 * @var bool
	 */
	public $debug = false;

	/**
	 * @var bool
	 */
	public $clearCache = false;

	/**
	 * @var bool
	 */
	public $useCache = false;

	public static function fromArray(array $options): self {
		$cliOptions = new self();
		$cliOptions->configPath = $options['config'];

		if (isset($options['linter'])) {
			$cliOptions->linter = $options['linter'];
		}
		if (isset($options['svn'])) {
			$cliOptions->svnMode = true;
		}
		if (isset($options['git-unstaged'])) {
			$cliOptions->gitUnstaged = true;
		}
		if (isset($options['git-staged'])) {
			$cliOptions->gitStaged = true;
		}
		if (isset($options['git-base'])) {
			$cliOptions->gitBase = $options['git-base'];
		}
		if (isset($options['report'])) {
			$cliOptions->reporter = $options['report'];
		}
		if (isset($options['debug'])) {
			$cliOptions->reporter = true;
		}
		if (isset($options['clearCache'])) {
			$cliOptions->clearCache = true;
		}
		if (isset($options['cache'])) {
			$cliOptions->useCache = true;
		}
		if (isset($options['no-cache'])) {
			$cliOptions->useCache = false;
		}
		return $cliOptions;
	}
}
