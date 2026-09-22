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
- Composer (installs JSON Schema, YAML, CommonMark, CSS and process libraries)
- PHPUnit or Behat in the consuming project for the corresponding test runner
- [schematter 0.2.0](https://github.com/iwe-org/schematter) for experimental Markdown definitions

## Supported Syntax

| Area | Support |
| --- | --- |
| Configuration | YAML with JSON Schema validation |
| Definition documents | YAML; experimental Markdown with document-schema validation |
| Specifications | EARS basic and complex patterns; syntax validation and source/test links |
| Sources | HTML/XML CSS, RFC XML, Markdown CSS, JSONPath subset, text lines; custom extensions |
| Test selection | PHPUnit `Class::method`, Behat `file.feature:line`; custom runners |
| Coverage | Overall, per source and changed units; supported/unsupported/uncovered counts |

See [document formats](docs/format.md), [lint rules](docs/lint.md) and
[traceability and extension contracts](docs/traceability.md) for the supported
boundaries and authoring conventions.

## Installation

```bash
composer require --dev k-kinzal/requirements
```

## Usage

Create `requirements.yaml` and definition files following the
[complete example](examples/requirements.yaml), then run:

```bash
vendor/bin/requirements lint
vendor/bin/requirements check
vendor/bin/requirements coverage
vendor/bin/requirements spec
vendor/bin/requirements list --without-source
vendor/bin/requirements format --check
```

See [CLI and CI gates](docs/cli.md) for options, filtering and exit codes. Source
snapshots are optional untracked caches; add `.requirements-cache/` to `.gitignore`.
Baselines contain fingerprints rather than copies of source documents.

In this monorepo, run `composer install` in `packages/requirements` and use
`php ../requirements/bin/requirements` from the consuming package. To develop this
package, install schematter, then run `composer test` and `composer lint`. The suite
executes real PHPUnit, Behat and document-schema validation processes.

## License

MIT License. See [LICENSE](LICENSE) for details.
