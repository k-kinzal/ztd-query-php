# Command Line

```console
vendor/bin/deliver <query> [options]
```

The command finds candidate values for one [query](queries.md) without executing the selected PHP files or their autoloaders. Run `vendor/bin/deliver --help` for options.

## Example

For a file `src/Answer.php` containing:

```php
<?php
namespace App;

function answer(): int
{
    return 42;
}
```

Select its return value:

```console
vendor/bin/deliver 'App\answer#return' --path=src
```

This reports `42` as an `analyzed` integer. See [reports](reports.md) for output formats and unresolved values.

## Options

| Option | Description |
|--------|-------------|
| `-c`, `--config=FILE` | Read a YAML [configuration](configuration.md). Omit to use defaults. |
| `--path=DIR` | Search root. Default: working directory. Repeat for multiple roots. |
| `--include=PATTERN` | Root-relative include wildcard. Default: `**/*.php`. Repeat to combine patterns. |
| `--exclude=PATTERN` | Root-relative exclude wildcard or directory subtree. Default: none. Repeat to combine patterns. |
| `--php-version=VERSION` | Target PHP `major.minor` version. Default: `8.3`. |
| `--format=FORMAT` | `text` or `json`. Default: `text`. |
| `-q`, `--quiet`, `--ansi`, `--no-ansi` | Control terminal output. |

CLI options override configuration values. Repeated options replace the whole configured list. Paths supplied on the command line are relative to the working directory.

## File selection

By default, the command selects `**/*.php` under the working directory. Use `--path` to select the directories containing the code and its dependencies. All selected files must be readable and parseable. Symbolic links are skipped, and no directory is excluded implicitly.

Patterns are relative to each search root. `*` and `?` match within one path component; `**` matches across zero or more directories. A file must match an include pattern and no exclude pattern. A directory exclusion also excludes its descendants.

```console
vendor/bin/deliver 'App\answer#return' --path=src --path=lib --include='**/*.php' --exclude='Generated/**'
```

Quote queries and wildcard patterns so the shell passes them unchanged.

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Report written after completed exploration. Partial candidates and empty result collections are valid. |
| `1` | Exploration interrupted, analysis or reporting failed, or the command invocation was rejected. |
| `2` | Invalid configuration, query or file selection, including no matching source files. |
