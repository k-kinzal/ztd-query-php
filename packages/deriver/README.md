# Deriver

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/deriver.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/deriver)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-deriver-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/deriver/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Deriver finds candidate values of PHP expressions without executing application code. Results retain unresolved inputs and the evidence behind each candidate.

Candidates describe value alternatives without proving that their control-flow conditions hold. For example, `return false ? 1 : 2` yields both `1` and `2`. Conditions and loop counts are not evaluated merely to select candidates. Applications can supply evaluators or evaluate and filter the returned candidates themselves. See [candidate dependencies](docs/queries.md#candidate-dependencies).

## Requirements

- PHP 8.1+

## Getting Started

```console
$ composer require --dev k-kinzal/deriver
$ vendor/bin/deliver --help
Description:
  Derive candidate values from PHP source without executing it.

Usage:
  deliver [options] [--] <query>

Arguments:
  query                          Selector, for example App\answer#return.

Options:
  -c, --config=CONFIG            Load a YAML configuration file.
      --path=PATH                Search root; repeat for multiple directories. Defaults to the current directory. (multiple values allowed)
      --include=INCLUDE          Root-relative include wildcard; repeat to combine patterns. Defaults to **/*.php. (multiple values allowed)
      --exclude=EXCLUDE          Root-relative exclude wildcard; exclusions win. (multiple values allowed)
      --php-version=PHP-VERSION  Target PHP major.minor version; defaults to the library setting.
      --format=FORMAT            Report format: text or json. Defaults to text.
  -h, --help                     Display help for the given command. When no command is given display help for the deliver command
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi|--no-ansi           Force (or disable --no-ansi) ANSI output
  -n, --no-interaction           Do not ask any interactive question
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

See the [command line](docs/cli.md), [configuration](docs/configuration.md), [queries](docs/queries.md), and [reports](docs/reports.md).

## License

MIT License. See [LICENSE](LICENSE) for details.
