# SQL Semantics for PostgreSQL

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--postgres-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-postgres/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for PostgreSQL adds PostgreSQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics): the typed statement models of the official PostgreSQL grammars and the PostgreSQL rules for schema binding. Installing it also installs the shared SQL Semantics runtime, and `Dialect::PostgreSql` selects PostgreSQL in the runtime's `Semantics`, `Schema`, and `Binder`. No database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension
- PostgreSQL 16–17

## Support Syntax

The following grammar versions are supported. Pass the version tag as the second argument of `Semantics` or the third argument of `Schema`; omitting it uses the default.

| Version | Version tag | Default |
|---------|-------------|---------|
| 17.2 | `pg-17.2` | Yes |

## Installation

```bash
composer require k-kinzal/sql-semantics-postgres
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$statement = (new Semantics(Dialect::PostgreSql))->analyze("INSERT INTO users (id, name) VALUES (1, 'Alice') RETURNING id");

$statement->toString(); // "INSERT INTO users ( id , name ) VALUES( 1 , 'Alice' ) RETURNING id"
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics) for statement models and schema binding.

## License

MIT License. See [LICENSE](LICENSE) for details.
