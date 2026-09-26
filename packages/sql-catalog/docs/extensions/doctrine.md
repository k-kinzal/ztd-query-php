# Doctrine DBAL

The `doctrine` extension catalogs SQL sent through a Doctrine DBAL `Connection` and its prepared `Statement`. Enable it with `--extension`:

```console
vendor/bin/sql-catalog --extension pdo,doctrine src/
```

```php
use Doctrine\DBAL\Connection;

function findUser(Connection $connection, int $id): array
{
    return $connection->fetchAllAssociative('SELECT * FROM users WHERE id = :id', ['id' => $id]);
}
```

```
SELECT * FROM users WHERE id = :id
:id = int
```

## Calls

| Sink ID | Call | Reads |
|---------|------|-------|
| `doctrine.executeQuery` | `Connection::executeQuery($sql, $params)` | The SQL and its bound values. |
| `doctrine.executeStatement` | `Connection::executeStatement($sql, $params)` | The SQL and its bound values. |
| `doctrine.executeCacheQuery` | `Connection::executeCacheQuery($sql, $params)` | The SQL and its bound values. |
| `doctrine.fetchAllAssociative`, `doctrine.fetchAllKeyValue`, `doctrine.fetchAllNumeric`, `doctrine.fetchAssociative`, `doctrine.fetchNumeric`, `doctrine.fetchFirstColumn`, `doctrine.fetchOne`, `doctrine.iterateAssociative` | The `Connection` method of the same name, with `($sql, $params)` | The SQL and its bound values. |
| `doctrine.prepare` | `Connection::prepare($sql)` | The SQL. The returned `Statement` collects the values bound to it. |
| `doctrine.statement.bindValue` | `Statement::bindValue($param, $value)` | One bound value. |
| `doctrine.statement.executeQuery` | `Statement::executeQuery($params)` | Bound values. |

A call whose SQL does not say what it does is reported with the kind the method implies: the `fetch*`, `iterate*` and `executeCacheQuery` methods report `select`.

## Receivers

A call is recognised when its receiver is a `Doctrine\DBAL\Connection` or `Doctrine\DBAL\Statement`, or a subclass of one declared in the analyzed paths. Type the connection where it is received, for example as a constructor parameter, so the analyzer can identify it.

## Limits

- The Query Builder is not modelled. `$connection->createQueryBuilder()->...->executeQuery()` is reported with the sink `unmatched` and the finding `call-not-analyzed`.
- `insert()`, `update()` and `delete()` of `Connection` build their SQL from arguments and are not catalogued.
- The `$types` argument is not read. An array bound with `ArrayParameterType` is reported as one placeholder with an `array` value, as written, not expanded.
- `Statement::executeStatement()` is not a sink, but the values bound with `bindValue()` before it are still attached.
- The ORM, DQL and `EntityManager` are not covered.
