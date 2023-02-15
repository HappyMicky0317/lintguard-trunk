<?php
declare(strict_types=1);

namespace LintGuard\Cli;

use LintGuard\NoChangesException;
use LintGuard\Reporter;
use LintGuard\JsonReporter;
use LintGuard\FullReporter;
use LintGuard\LintMessages;
use LintGuard\DiffLineMap;
use LintGuard\ShellException;
use LintGuard\ShellOperator;
use LintGuard\UnixShell;
use LintGuard\CacheManager;
use function LintGuard\{getNewPhpcsMessages, getNewPhpcsMessagesFromFiles, getVersion};
use function LintGuard\SvnWorkflow\{getSvnUnifiedDiff, getSvnFileInfo, isNewSvnFile, getSvnBasePhpcsOutput, getSvnNewPhpcsOutput, getSvnRevisionId};
use function LintGuard\GitWorkflow\{getGitMergeBase, getGitUnifiedDiff, isNewGitFile, getGitBasePhpcsOutput, getGitNewPhpcsOutput, validateGitFileExists, getNewGitFileHash, getOldGitFileHash};

function getDebug(CliOptions $options): callable {
	$debugEnabled = $options->debug;
	return function(...$outputs) use ($debugEnabled) {
		if (! $debugEnabled) {
			return;
		}
		foreach ($outputs as $output) {
			fwrite(STDERR, (is_string($output) ? $output : var_export($output, true)) . PHP_EOL);
		}
	};
}

function printError(string $output): void {
	fwrite(STDERR, 'lintguard: An error occurred.' . PHP_EOL);
	fwrite(STDERR, 'ERROR: ' . $output . PHP_EOL);
}

function printErrorAndExit(string $output): void {
	printError($output);
	fwrite(STDERR, PHP_EOL . 'Run "lintguard --help" for usage information.'. PHP_EOL);
	exit(1);
}

function getLongestString(array $strings): int {
	return array_reduce($strings, function(int $length, string $string): int {
		return ($length > strlen($string)) ? $length : strlen($string);
	}, 0);
}

function printTwoColumns(array $columns, string $indent): void {
	$longestFirstCol = getLongestString(array_keys($columns));
	echo PHP_EOL;
	foreach ($columns as $firstCol => $secondCol) {
		printf("%s%{$longestFirstCol}s\t%s" . PHP_EOL, $indent, $firstCol, $secondCol);
	}
	echo PHP_EOL;
}

function printVersion(): void {
	$version = getVersion();
	echo <<<EOF
lintguard version {$version}

EOF;
}

function printHelp(): void {
	echo <<<EOF
Run various code linters but only report messages caused by recent changes.

You must specify a linter to use with the `--linter <LINTER>` option. For
example, to run phpcs, use `--linter phpcs`.

Then you must provide the previous and new versions of the linter output and
the diff showing the changes between the two.

lintguard can be run in two modes: manual or automatic (recommended).

Manual Mode:

	In manual mode, three arguments are required to collect the information
	needed:

EOF;

	printTwoColumns([
		'--diff <FILE>' => 'A file containing a unified diff of the file changes.',
		'--previous-lint <FILE>' => 'A file containing the JSON output of the linter run on the unchanged files.',
		'--new-lint <FILE>' => 'A file containing the JSON output of the linter run on the changed files.',
	], "	");

	echo <<<EOF

Automatic Mode:

	Automatic mode can scan multiple files and will gather the required data
	itself if you specify the version control system (you must run lintguard
	from within the version-controlled directory for this to work):

EOF;

	printTwoColumns([
		'--svn' => 'Assume svn-versioned files.',
		'--git-staged' => 'Compare the staged git version to the HEAD version.',
		'--git-unstaged' => 'Compare the git working copy version to the staged (or HEAD) version.',
		'--git-base <OBJECT>' => 'Compare the git HEAD version to version found in OBJECT which can be a branch, commit, or other git object.',
	], "	");

	echo <<<EOF

	Example: lintguard --linter phpcs --svn

Options:

	Each linter uses a different method to get its list of files and other
	configration options. While this library comes with a set of defaults, you
	should probably create a config file to specify the options you want to use.

	By default, linters will be run on an entire project, but if you want to run
	the linter on a specific file or set of files, you should customize the
	options.

	All modes support the following options.

EOF;

	printTwoColumns([
		'--config <FILE>' => 'Path to the config file. Uses .lintguardrc.json in the current directory otherwise.',
		'--report <REPORTER>' => 'The output reporter to use. One of "human" (default) or "json".',
		'--ignore <PATTERNS>' => 'A comma separated list of patterns to ignore files and directories.',
		'--debug' => 'Enable debug output.',
		'--help' => 'Print this help.',
		'--version' => 'Print the current version.',
		'--cache' => 'Cache phpcs output for improved performance (no-cache will still disable this).',
		'--no-cache' => 'Disable caching of phpcs output (does not remove existing cache).',
		'--clear-cache' => 'Clear the cache before running.',
	], "	");
	echo <<<EOF
Config file:

	By default, `lintguard` will look for a file named `.lintguardrc.json`
	in the directory where it is invoked. You can instead specify a path to a
	config file with the `--config <FILE>` option.

	If using automatic mode, this script requires two shell commands: the version
	control program ('svn' or 'git') and the linter (eg: 'phpcs'). If those
	commands are not in your PATH or you would like to override them, you can use
	the `.lintguardrc.json` config file to specify the full path for each one.

	Each linter accepts two options: `command` (a string with the path to the
	linter) and `args` (an array of arguments to pass to the linter).

	All settings in the config file are optional, but here's example values:

	{
		"version-control": {
			"svn": "/usr/bin/svn",
			"git": "/usr/local/bin/git"
		},
		"linter-options": {
			"phpcs": {
				"command": "/usr/local/bin/phpcs",
				"args": [ "--standard=MyCustomStandard", "--ignore=tests", "**/*.php" ]
			},
			"tsc": {
				"command": "/usr/local/bin/tsc",
				"args": [ "-p .tsconfig.json" ]
			}
		}
	}

EOF;
}

function getReporter(string $reportType): Reporter {
	switch ($reportType) {
		case 'human':
			return new FullReporter();
		case 'json':
			return new JsonReporter();
	}
	printErrorAndExit("Unknown Reporter '{$reportType}'");
	throw new \Exception("Unknown Reporter '{$reportType}'"); // Just in case we don't exit for some reason.
}

function runManualWorkflow(string $diffFile, string $previousLintOut, string $newLintOut): LintMessages {
	try {
		return LintMessages::getNewMessages(
			$diffFile,
			$previousLintOut,
			$newLintOut
		);
	} catch (\Exception $err) {
		printErrorAndExit($err->getMessage());
		throw $err; // Just in case we don't exit
	}
}

function runSvnWorkflow(CliOptions $options, ShellOperator $shell, CacheManager $cache): LintMessages {
	$svn = $options->config->svn;
	$phpcs = $options->config->phpcs;

	$debug = getDebug($cliOptions);

	try {
		$debug('validating executables');
		$shell->validateExecutableExists('svn', $svn);
		$shell->validateExecutableExists('phpcs', $phpcs);
		$debug('executables are valid');
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	loadCache($cache, $shell, $options);

	$messages = array_map(function(string $svnFile) use ($options, $shell, $cache, $debug): LintMessages {
		return runSvnWorkflowForFile($svnFile, $options, $shell, $cache, $debug);
	}, $svnFiles);

	saveCache($cache, $shell, $options);

	return LintMessages::merge($messages);
}

function runSvnWorkflowForFile(string $svnFile, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$svn = getenv('SVN') ?: 'svn';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		if (! $shell->isReadable($svnFile)) {
			throw new ShellException("Cannot read file '{$svnFile}'");
		}
		$svnFileInfo = getSvnFileInfo($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$unifiedDiff = getSvnUnifiedDiff($svnFile, $svn, [$shell, 'executeCommand'], $debug);
		$revisionId = getSvnRevisionId($svnFileInfo);
		$isNewFile = isNewSvnFile($svnFileInfo);

		$oldFilePhpcsOutput = '';
		if ( ! $isNewFile ) {
			$oldFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '') : null;
			if ($oldFilePhpcsOutput) {
				$debug("Using cache for old file '{$svnFile}' at revision '{$revisionId}' and standard '{$phpcsStandard}'");
			}
			if (! $oldFilePhpcsOutput) {
				$debug("Not using cache for old file '{$svnFile}' at revision '{$revisionId}' and standard '{$phpcsStandard}'");
				$oldFilePhpcsOutput = getSvnBasePhpcsOutput($svnFile, $svn, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
				if (isCachingEnabled($options)) {
					$cache->setCacheForFile($svnFile, 'old', $revisionId, $phpcsStandard ?? '', $oldFilePhpcsOutput);
				}
			}
		}

		$newFileHash = $shell->getFileHash($svnFile);
		$newFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($svnFile, 'new', $newFileHash, $phpcsStandard ?? '') : null;
		if ($newFilePhpcsOutput) {
			$debug("Using cache for new file '{$svnFile}' at revision '{$revisionId}', hash '{$newFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $newFilePhpcsOutput) {
			$debug("Not using cache for new file '{$svnFile}' at revision '{$revisionId}', hash '{$newFileHash}', and standard '{$phpcsStandard}'");
			$newFilePhpcsOutput = getSvnNewPhpcsOutput($svnFile, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $debug);
			if (isCachingEnabled($options)) {
				$cache->setCacheForFile($svnFile, 'new', $newFileHash, $phpcsStandard ?? '', $newFilePhpcsOutput);
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsOutput = '';
	} catch( \Exception $err ) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit, like in tests
	}

	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages(
		$unifiedDiff,
		PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName),
		PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName)
	);
}

function runGitWorkflow(array $gitFiles, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	try {
		$debug('validating executables');
		$shell->validateExecutableExists('git', $git);
		$shell->validateExecutableExists('phpcs', $phpcs);
		$shell->validateExecutableExists('cat', $cat);
		$debug('executables are valid');
		if (isset($options['git-base']) && ! empty($options['git-base'])) {
			$options['git-base'] = getGitMergeBase($git, [$shell, 'executeCommand'], $options, $debug);
		}
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	loadCache($cache, $shell, $options);

	$phpcsMessages = array_map(function(string $gitFile) use ($options, $shell, $cache, $debug): PhpcsMessages {
		return runGitWorkflowForFile($gitFile, $options, $shell, $cache, $debug);
	}, $gitFiles);

	saveCache($cache, $shell, $options);

	return PhpcsMessages::merge($phpcsMessages);
}

function runGitWorkflowForFile(string $gitFile, array $options, ShellOperator $shell, CacheManager $cache, callable $debug): PhpcsMessages {
	$git = getenv('GIT') ?: 'git';
	$phpcs = getenv('PHPCS') ?: 'phpcs';
	$cat = getenv('CAT') ?: 'cat';

	$phpcsStandard = $options['standard'] ?? null;
	$phpcsStandardOption = $phpcsStandard ? ' --standard=' . escapeshellarg($phpcsStandard) : '';

	try {
		validateGitFileExists($gitFile, $git, [$shell, 'isReadable'], [$shell, 'executeCommand'], $debug);
		$unifiedDiff = getGitUnifiedDiff($gitFile, $git, [$shell, 'executeCommand'], $options, $debug);
		$isNewFile = isNewGitFile($gitFile, $git, [$shell, 'executeCommand'], $options, $debug);
		$oldFilePhpcsOutput = '';
		if (! $isNewFile) {
			$oldFileHash = getOldGitFileHash($gitFile, $git, $cat, [$shell, 'executeCommand'], $options, $debug);
			$oldFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($gitFile, 'old', $oldFileHash, $phpcsStandard ?? '') : null;
			if ($oldFilePhpcsOutput) {
				$debug("Using cache for old file '{$gitFile}' at hash '{$oldFileHash}' with standard '{$phpcsStandard}'");
			}
			if (! $oldFilePhpcsOutput) {
				$debug("Not using cache for old file '{$gitFile}' at hash '{$oldFileHash}' with standard '{$phpcsStandard}'");
				$oldFilePhpcsOutput = getGitBasePhpcsOutput($gitFile, $git, $phpcs, $phpcsStandardOption, [$shell, 'executeCommand'], $options, $debug);
				if (isCachingEnabled($options)) {
					$cache->setCacheForFile($gitFile, 'old', $oldFileHash, $phpcsStandard ?? '', $oldFilePhpcsOutput);
				}
			}
		}

		$newFileHash = getNewGitFileHash($gitFile, $git, $cat, [$shell, 'executeCommand'], $options, $debug);
		$newFilePhpcsOutput = isCachingEnabled($options) ? $cache->getCacheForFile($gitFile, 'new', $newFileHash, $phpcsStandard ?? '') : null;
		if ($newFilePhpcsOutput) {
			$debug("Using cache for new file '{$gitFile}' at hash '{$newFileHash}', and standard '{$phpcsStandard}'");
		}
		if (! $newFilePhpcsOutput) {
			$debug("Not using cache for new file '{$gitFile}' at hash '{$newFileHash}', and standard '{$phpcsStandard}'");
			$newFilePhpcsOutput = getGitNewPhpcsOutput($gitFile, $git, $phpcs, $cat, $phpcsStandardOption, [$shell, 'executeCommand'], $options, $debug);
			if (isCachingEnabled($options)) {
				$cache->setCacheForFile($gitFile, 'new', $newFileHash, $phpcsStandard ?? '', $newFilePhpcsOutput);
			}
		}
	} catch( NoChangesException $err ) {
		$debug($err->getMessage());
		$unifiedDiff = '';
		$oldFilePhpcsOutput = '';
		$newFilePhpcsOutput = '';
	} catch(\Exception $err) {
		$shell->printError($err->getMessage());
		$shell->exitWithCode(1);
		throw $err; // Just in case we do not actually exit
	}

	$debug('processing data...');
	$fileName = DiffLineMap::getFileNameFromDiff($unifiedDiff);
	return getNewPhpcsMessages($unifiedDiff, PhpcsMessages::fromPhpcsJson($oldFilePhpcsOutput, $fileName), PhpcsMessages::fromPhpcsJson($newFilePhpcsOutput, $fileName));
}

function reportMessagesAndExit(PhpcsMessages $messages, string $reportType, array $options): void {
	$reporter = getReporter($reportType);
	echo $reporter->getFormattedMessages($messages, $options);
	exit($reporter->getExitCode($messages));
}

function isCachingEnabled(array $options): bool {
	if (isset($options['no-cache'])) {
		return false;
	}
	if (isset($options['cache'])) {
		return true;
	}
	return false;
}

function loadCache(CacheManager $cache, ShellOperator $shell, array $options): void {
	if (isCachingEnabled($options)) {
		try {
			$cache->load();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			// If there is an invalid cache, we should clear it to be safe
			$shell->printError('An error occurred reading the cache so it will now be cleared. Try running your command again.');
			$cache->clearCache();
			saveCache($cache, $shell, $options);
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}

	if (isset($options['clear-cache'])) {
		$cache->clearCache();
		try {
			$cache->save();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}
}

function saveCache(CacheManager $cache, ShellOperator $shell, array $options): void {
	if (isCachingEnabled($options)) {
		try {
			$cache->save();
		} catch( \Exception $err ) {
			$shell->printError($err->getMessage());
			$shell->printError('An error occurred saving the cache. Try running with caching disabled.');
			$shell->exitWithCode(1);
			throw $err; // Just in case we do not actually exit, like in tests
		}
	}
}
