# SQL Semantics for MySQL

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--mysql-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-mysql/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for MySQL adds MySQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics): the typed statement models of the official MySQL grammars and the MySQL rules for schema binding. Installing it also installs the shared SQL Semantics runtime, and `Dialect::MySql` selects MySQL in the runtime's `Semantics`, `Schema`, and `Binder`. No database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are supported. Pass the version tag as the second argument of `Semantics` or the third argument of `Schema`; omitting it uses the default. State declarations support all listed versions; SELECT binding with `Binder` requires MySQL 8.0 or later.

| Version | Version tag | Default |
|---------|-------------|---------|
| 5.6.51 | `mysql-5.6.51` | |
| 5.7.44 | `mysql-5.7.44` | |
| 8.0.44 | `mysql-8.0.44` | |
| 8.1.0 | `mysql-8.1.0` | |
| 8.2.0 | `mysql-8.2.0` | |
| 8.3.0 | `mysql-8.3.0` | |
| 8.4.7 | `mysql-8.4.7` | Yes |
| 9.0.1 | `mysql-9.0.1` | |
| 9.1.0 | `mysql-9.1.0` | |

## Installation

```bash
composer require k-kinzal/sql-semantics-mysql
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$statement = (new Semantics(Dialect::MySql))->analyze("INSERT INTO users (id, name) VALUES (1, 'Alice') ON DUPLICATE KEY UPDATE name = 'Alice'");

$statement->toString(); // "INSERT INTO users ( id , name ) VALUES( 1 , 'Alice' ) ON DUPLICATE KEY UPDATE name = 'Alice'"
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics) for statement models and schema binding.

## License

MIT License. See [LICENSE](LICENSE) for details.
