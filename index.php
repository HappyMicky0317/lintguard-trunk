<?php
declare(strict_types=1);

namespace LintGuard;

// Classes
require_once __DIR__ . '/LintGuard/CliOptions.php';
require_once __DIR__ . '/LintGuard/Config.php';
require_once __DIR__ . '/LintGuard/LinterOptions.php';
require_once __DIR__ . '/LintGuard/DiffLine.php';
require_once __DIR__ . '/LintGuard/DiffLineType.php';
require_once __DIR__ . '/LintGuard/DiffLineMap.php';
require_once __DIR__ . '/LintGuard/LintMessage.php';
require_once __DIR__ . '/LintGuard/LintMessages.php';
require_once __DIR__ . '/LintGuard/PhpcsMessages.php';
require_once __DIR__ . '/LintGuard/PhpcsMessagesHelpers.php';
require_once __DIR__ . '/LintGuard/Reporter.php';
require_once __DIR__ . '/LintGuard/JsonReporter.php';
require_once __DIR__ . '/LintGuard/FullReporter.php';
require_once __DIR__ . '/LintGuard/XmlReporter.php';
require_once __DIR__ . '/LintGuard/NoChangesException.php';
require_once __DIR__ . '/LintGuard/ShellException.php';
require_once __DIR__ . '/LintGuard/ShellOperator.php';
require_once __DIR__ . '/LintGuard/UnixShell.php';
require_once __DIR__ . '/LintGuard/CacheEntry.php';
require_once __DIR__ . '/LintGuard/CacheObject.php';
require_once __DIR__ . '/LintGuard/CacheInterface.php';
require_once __DIR__ . '/LintGuard/CacheManager.php';
require_once __DIR__ . '/LintGuard/FileCache.php';

// Function-only files
require_once __DIR__ . '/LintGuard/functions.php';
require_once __DIR__ . '/LintGuard/Cli.php';
require_once __DIR__ . '/LintGuard/SvnWorkflow.php';
require_once __DIR__ . '/LintGuard/GitWorkflow.php';
