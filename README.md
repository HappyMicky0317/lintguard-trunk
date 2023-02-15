Run various code linters but only report messages caused by recent changes.

## IN PROGRESS

This is not ready yet.

## What is this for?

Let's say that you need to add a feature to a large legacy file which has many linter errors. If you try to run your linters on that file, there may be so much noise it's impossible to notice any errors which you may have added yourself.

Using this in place of your linter will report messages that apply only to the changes you have made and ignores any messages that were there previously.

## Installation

```
composer global require sirbrillig/lintguard
```

## CLI Usage

👩‍💻👩‍💻👩‍💻

First you must specify a linter to use using the `--linter <linter>` option.

Next, you need to be able to provide data about the previous and current versions of your code. `lintguard` can get this data itself using svn or git.

Here's an example using `lintguard` with the `--svn` option:

```
lintguard --linter phpcs --svn
```

This will output something like:

```
file.php
 76:3   warning    Variable $foobar is undefined.
 78:16  warning    Variable $barfoo is undefined.

2 problems (0 errors, 2 warnings)
```

Or, with `--report json`:

```json
{
  "totals": {
    "errors": 0,
    "warnings": 2,
  },
  "files": {
    "file.php": {
      "errors": 0,
      "warnings": 2,
      "messages": [
        {
          "line": 76,
          "message": "Variable $foobar is undefined.",
          "source": "VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable",
          "severity": 5,
          "type": "warning",
          "column": 3
        },
        {
          "line": 78,
          "message": "Variable $barfoo is undefined.",
          "source": "VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable",
          "severity": 5,
          "type": "warning",
          "column": 16
        }
      ]
    }
  }
}
```

If the file was versioned by git, we can do the same with the various git options:

```
lintguard --linter phpcs --git-unstaged
```

When using git mode, you must specify `--git-staged`, `--git-unstaged`, or `--git-base`.

`--git-staged` compares the currently staged changes (as the new version of the files) to the current HEAD (as the previous version of the files). This is similar to `git diff --staged`.

`--git-unstaged` compares the current (unstaged) working copy changes (as the new version of the files) to the either the currently staged changes, or if there are none, the current HEAD (as the previous version of the files). This is similar to `git diff`.

`--git-base`, followed by a git object, compares the current HEAD (as the new version of the files) to the specified [git object](https://git-scm.com/book/en/v2/Git-Internals-Git-Objects) (as the previous version of the file) which can be a branch name, a commit, or some other valid git object.

```
git checkout add-new-feature
lintguard --linter phpcs --git-base trunk
```

**Note that the output of `lintguard` will be in the same format no matter which linter you use; this is a format specific to lintguard and likely will not match the typical output of the underlying linter being used, although you can write custom reporters.**

### CLI Options

Each linter uses a different method to get its list of files and other configration options. While this library comes with a set of defaults, you should probably create a [config file](#Configfile) to specify the options you want to use.

By default, linters will be run on an entire project, but if you want to run the linter on a specific file or set of files, you should customize the options.

You can use `--report` to customize the output type. `human` (the default) is human-readable and `json` prints a JSON object as shown above.

The `--cache` option will enable caching of linter output and can significantly improve performance for slow linters or when running with high frequency. There are actually two caches: one for the scan of the previous version of the file and one for the scan of the new version. The previous version output cache is invalidated when the .version control revision change version of the file changes. The new version output cache is invalidated when the new file changes.

The `--no-cache` option will disable the cache if it's been enabled.

The `--clear-cache` option will clear the cache before running. This works with or without caching enabled.

### Config file

By default, `lintguard` will look for a file named `.lintguardrc.json` in the directory where it is invoked. You can instead specify a path to a config file with the `--config <path-to-config>` option.

Each linter accepts two options: `command` (a string with the path to the linter) and `args` (an array of arguments to pass to the linter).

All settings in the config file are optional, but here's example values:

```json
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
```

## Running Tests

Run the following commands in this directory to run the built-in test suite:

```
composer install
composer test
```

You can also run linting and static analysis:

```
composer lint
composer phpstan
```

## Inspiration

This is based on my previous work in [phpcs-changed](https://github.com/sirbrillig/phpcs-changed).
