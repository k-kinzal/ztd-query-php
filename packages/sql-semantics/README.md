# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics turns SQL into immutable semantic data: statement operations, result fields, relation occurrences, column references, and type facts. `Semantics::analyze()` is the single entry point with or without a catalog. Models reconstruct SQL from their values and support persistent changes guarded by assertions. No database connection is needed.

The semantic implementation is incomplete. It currently covers the SELECT, INSERT, and single-table DELETE forms described in [statement models](docs/statements.md). Other constructs fail explicitly; parser acceptance and grammar coverage are not claims of semantic coverage. See the [semantic model contract](docs/semantic-model.md).

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following parser grammar releases are available. Semantic lowering has the narrower boundary documented above. Pass the dialect of your database package and, optionally, the version tag to `Semantics`; omitting the version tag uses the default for that database.

### MySQL

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

### PostgreSQL

| Version | Version tag | Default |
|---------|-------------|---------|
| 17.2 | `pg-17.2` | Yes |

### SQLite

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

## Installation

Install the package of your database; it installs this runtime.

MySQL:

```bash
composer require k-kinzal/sql-semantics-mysql
```

PostgreSQL:

```bash
composer require k-kinzal/sql-semantics-postgres
```

SQLite:

```bash
composer require k-kinzal/sql-semantics-sqlite
```

Each package provides its dialect: `SqlSemantics\Platform\MySql\Dialect::MySql`, `SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql`, or `SqlSemantics\Platform\Sqlite\Dialect::Sqlite`.

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Projection\Field;
use SqlSemantics\Semantic\Statement\Select;

$statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT foo FROM bar');
assert($statement instanceof Select);

$statement->field('foo')->type->name; // 'unknown': the catalog was not supplied
$statement->tables[0]->name->name->value; // 'bar'
$statement->toString(); // 'SELECT foo FROM bar'

$fields = $statement->fields()->addField(new Field($statement->scope->column(new Name('label'))));
$updated = $statement->withFields($fields);
$updated->toString(); // 'SELECT foo, label FROM bar'
```

Pass an array of table declarations as the second argument to resolve fields to their exact table and column objects. Omitting the array means an absent catalog; `[]` means a known empty catalog. Missing and ambiguous references are distinct from undetermined facts.

See [reference resolution](docs/binding.md) for catalog construction and [statement models](docs/statements.md) for immutable updates and separate INSERT source types.

## License

MIT License. See [LICENSE](LICENSE) for details.
