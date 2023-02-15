<?php
declare(strict_types=1);

namespace LintGuard;

class LinterOptions {
	/**
	 * @var string
	 */
	public $command;

	/**
	 * @var string[]
	 */
	public $args = [];

	public function __construct(string $command, array $args) {
		$this->command = $command;
		$this->args = $args;
	}
}
