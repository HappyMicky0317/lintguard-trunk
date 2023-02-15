<?php

declare(strict_types=1);

namespace LintGuardTests;

use LintGuard\XmlReporter;

class TestXmlReporter extends XmlReporter {
	protected function getPhpcsVersion(): string {
		return '1.2.3';
	}
}
