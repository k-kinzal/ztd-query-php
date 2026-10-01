# SQL Semantics for SQLite

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--sqlite-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-sqlite/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for SQLite adds SQLite to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics): semantic lowering from the official SQLite grammars and the SQLite rules for schema binding. Installing it also installs the shared SQL Semantics runtime, and `Dialect::Sqlite` selects SQLite in the runtime's `Semantics`. No database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following parser grammar releases are available. Pass the version tag as the second argument of `Semantics`; omitting it uses the default. Semantic lowering has the narrower scope linked below.

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

## Installation

```bash
composer require k-kinzal/sql-semantics-sqlite
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$statement = (new Semantics(Dialect::Sqlite))->analyze("INSERT INTO users (id, name) VALUES (1, 'Alice')");

$statement->toString(); // "INSERT INTO users (id, name) VALUES (1, 'Alice')"
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics) for statement models and schema binding.

Semantic coverage is limited to the operations documented in [statement models](../sql-semantics/docs/statements.md). `Semantics::analyze()` performs schema-free and schema-aware analysis; the separate `Binder` API and generated grammar models have been removed.

## License

MIT License. See [LICENSE](LICENSE) for details.
