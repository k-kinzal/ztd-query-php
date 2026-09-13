# PostgreSQL COPY

Use the adapter's COPY methods to import or export shadow rows. Raw SQL `COPY` is rejected because the server would bypass the shadow.

| Operation | PDO driver method | Typed alias |
| --- | --- | --- |
| Export encoded lines | `pgsqlCopyToArray()` | `copyToArray()` |
| Import encoded lines | `pgsqlCopyFromArray()` | `copyFromArray()` |
| Export to a file | `pgsqlCopyToFile()` | `copyToFile()` |
| Import from a file | `pgsqlCopyFromFile()` | `copyFromFile()` |

The following example uses a PostgreSQL test connection. Set `PG_DSN`, `PG_USER`, and `PG_PASSWORD` for that database. The example creates an isolated schema for the physical table and removes it afterward.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO(getenv('PG_DSN'), getenv('PG_USER'), getenv('PG_PASSWORD'));
$schema = 'copy_example_' . bin2hex(random_bytes(4));
$native->exec('CREATE SCHEMA ' . $schema);
$native->exec('SET search_path TO ' . $schema);
try {
    $native->exec('CREATE TABLE copy_users (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo = ZtdPdo::fromPdo($native);
    assert($pdo->pgsqlCopyFromArray('copy_users', ["1\tAda\n", "2\tGrace\n"]));
    $lines = $pdo->pgsqlCopyToArray('copy_users');
    sort($lines);
    assert($lines === ["1\tAda\n", "2\tGrace\n"]);
    assert($native->query('SELECT COUNT(*) FROM copy_users')->fetchColumn() === 0);
} finally {
    $native->exec('DROP SCHEMA ' . $schema . ' CASCADE');
}
```

The default field separator is a tab and the null marker is `\N`. The optional `fields` argument specifies a column list. Import accepts an array or a traversable collection of encoded strings; invalid argument or row types raise `ZtdPdoException`, which extends `PDOException`. COPY methods require the PostgreSQL platform package and a known table schema.
