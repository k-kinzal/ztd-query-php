# Requirements

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-requirements-0969da?logo=php&logoColor=white)](docs/cli.md)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

Requirements connects source text, EARS specifications and executable tests for PHP
projects. It finds changed quotations, unaccounted source units, unsupported
behaviour and specifications without sources. Requirements are optional upstream
records; specifications and their verification are the central model. Coverage is
limited to explicitly selected source scopes and does not prove semantic equivalence.

## Requirements

- PHP 8.1 or higher with DOM, libxml and mbstring (cURL is recommended for HTTP sources)
- Composer (installs the PHP libraries, including Symfony Console)
- PHPUnit or Behat in the consuming project for the corresponding test runner

## Supported Syntax

| Area | Support |
| --- | --- |
| Configuration | YAML with JSON Schema validation |
| Definition documents | YAML; experimental Markdown with attribute badges, quoted sources and navigable citations |
| Specifications | EARS basic and complex patterns; syntax validation and source/test links |
| Sources | HTML CSS / exact Text Fragments; XML/RFC/Markdown CSS; JSONPath subset; text lines; custom extensions |
| Test selection | PHPUnit `Class::method`, Behat `file.feature:line`; custom runners |
| Coverage | Overall, per source and changed units; supported/unsupported/uncovered counts |

See [document formats](docs/format.md), [lint rules](docs/lint.md) and
[traceability](docs/traceability.md) and [source/test extensions](docs/extensions.md) for the supported
boundaries and authoring conventions.

## Installation

```bash
composer require --dev k-kinzal/requirements
```

## Usage

Create `requirements.yaml` and definition files following the
[complete example](examples/yaml/requirements.yaml), then run:

```bash
vendor/bin/requirements --help
vendor/bin/requirements lint
vendor/bin/requirements check
vendor/bin/requirements coverage
vendor/bin/requirements spec
vendor/bin/requirements spec --no-test --without-source
vendor/bin/requirements format --check
```

Commands display terminal tables and offer per-command help, for example
`requirements coverage --help`. `spec` combines browsing and verification: its
Tests column shows passing / linked targets (`3/3`); `--no-test` shows `-/3` without
executing tests. Data-provider cases and outline examples are counted separately
in `--json` reports. Add `--json` for complete traceability records.
See [CLI and CI gates](docs/cli.md) for options, filtering and exit codes. Source
snapshots are optional untracked caches; add `.requirements-cache/` to `.gitignore`.
A coverage snapshot (`coverage --write-snapshot`) lists every unit in scope with a
fingerprint rather than a copy of the source document; a later run compares itself
with it to gate new or changed units.

In this monorepo, run `composer install` in `packages/requirements` and use
`php ../requirements/bin/requirements` from the consuming package. To develop this
package, run `composer test` and `composer lint`. The suite executes real PHPUnit,
Behat and custom extension processes. Markdown parsing and validation run entirely
in PHP; no external Markdown tool or additional runtime is required. See the
[readable Markdown example](examples/markdown/grammar.md) and
[extension example](examples/extensions/requirements.yaml).

## License

MIT License. See [LICENSE](LICENSE) for details.
