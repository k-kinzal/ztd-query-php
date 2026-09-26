# Requirements

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-requirements-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/requirements/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Requirements connects source text, EARS specifications and executable tests for PHP projects. It finds changed quotations, unaccounted source units, unsupported behaviour and specifications without sources. Requirements are optional upstream records; specifications and their verification are the central model. Coverage is limited to explicitly selected source scopes and does not prove semantic equivalence.

## Requirements

- PHP 8.1+ with the dom, libxml and mbstring extensions
- PHPUnit 10.5+ or Behat 3.14+ to run linked tests

## Getting Started

```console
$ composer require --dev k-kinzal/requirements
$ vendor/bin/requirements --help
Requirements

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display command help, or the application overview when no command is given.
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -c, --config=CONFIG   Path to the YAML project configuration. [default: "requirements.yaml"]
      --json            Write a JSON report to stdout instead of terminal tables.
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  check       Verify quotations against the declared source scopes.
  completion  Dump the shell completion script
  coverage    Show source coverage and enforce total and differential gates.
  format      Format YAML and Markdown definition documents.
  help        Display help for a command
  lint        Validate schemas, EARS syntax and cross-file traceability.
  list        List commands
  spec        Browse specifications and requirements, and verify linked tests unless --no-test is set.
```

## License

MIT License. See [LICENSE](LICENSE) for details.
