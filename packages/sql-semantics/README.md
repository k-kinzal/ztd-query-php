# SQL Semantics for PHP

SQL Semantics binds a statement to a database-specific, immutable semantic structure. Use the database package for the grammar, name resolution, concrete types and SQL representation.

| Database | Package | Shipped grammar releases |
| --- | --- | --- |
| MySQL | [sql-semantics-mysql](../sql-semantics-mysql/) | 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0 |
| PostgreSQL | [sql-semantics-postgres](../sql-semantics-postgres/) | 16.6, 17.2 |
| SQLite | [sql-semantics-sqlite](../sql-semantics-sqlite/) | 3.47.2 |

Requires PHP 8.1 or later. Install the appropriate dialect package to obtain its implementation and the shared interfaces.

    composer require k-kinzal/sql-semantics-sqlite

## Analyze a statement

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$sqlite = new Semantics();
$catalog = $sqlite->context(['CREATE TABLE accounts (id INTEGER, balance INTEGER)']);
$ir = $sqlite->analyze('SELECT id, accounts.id, balance - ?2 AS remaining FROM accounts', $catalog);

echo $ir->toString();
```

Both references to `id` address the same bound column slot. The emitted statement retains both result positions, their labels and the numbered binding.

The shared `Analyzer::analyze()` interface returns a `SemanticsIR`. Dialect packages expose the checked concrete values for construction and traversal. The same command or expression may have different concrete types and interpretation across databases.

See [supported IR and examples](docs/semantics-ir.md), [catalogs and resolution](docs/contexts.md), [the semantic model](docs/model.md), [SQL emission](docs/rendering.md), and [assurance and scope](docs/guarantees.md).
