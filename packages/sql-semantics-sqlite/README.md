# SQL Semantics for SQLite

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--sqlite-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-sqlite/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for SQLite adds SQLite to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics): the typed statement models of the official SQLite grammars and the SQLite rules for schema binding. Installing it also installs the shared SQL Semantics runtime, and `Dialect::Sqlite` selects SQLite in the runtime's `Semantics`, `SchemaBuilder`, and `Binder`. No database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension
- SQLite 3.x

## Support Syntax

The following grammar versions are supported. Pass the version tag as the second argument of `Semantics` or the third argument of `SchemaBuilder`; omitting it uses the default.

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

$statement = (new Semantics(Dialect::Sqlite))->analyze("INSERT OR REPLACE INTO users (id, name) VALUES (1, 'Alice')");

$statement->toString(); // "INSERT OR REPLACE INTO users( id , name ) VALUES( 1 , 'Alice' )"
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics) for statement models and schema binding.

## License

MIT License. See [LICENSE](LICENSE) for details.
