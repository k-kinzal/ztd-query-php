# PDO

The `pdo` extension catalogs SQL sent through `PDO` and `PDOStatement`. It is enabled by default.

```php
function findUser(PDO $db, int $id): array
{
    $statement = $db->prepare('SELECT * FROM users WHERE id = :id AND role = :role');
    $statement->bindValue(':id', $id, PDO::PARAM_INT);
    $statement->bindValue('role', 'admin');
    $statement->execute();

    return $statement->fetchAll();
}
```

```
SELECT * FROM users WHERE id = :id AND role = :role
:id = int
:role = 'admin'
```

## Calls

| Sink ID | Call | Reads |
|---------|------|-------|
| `pdo.query` | `PDO::query($sql)` | The SQL. |
| `pdo.exec` | `PDO::exec($sql)` | The SQL. |
| `pdo.prepare` | `PDO::prepare($sql)` | The SQL. The returned `PDOStatement` collects the values bound to it. |
| `pdo.statement.execute` | `PDOStatement::execute($params)` | Bound values, by position or by name. |
| `pdo.statement.bindValue` | `PDOStatement::bindValue($param, $value)` | One bound value. |
| `pdo.statement.bindParam` | `PDOStatement::bindParam($param, $var)` | One bound value, read at the call. |

A prepared statement is reported once, at `pdo.prepare`, with the values of every `execute()`, `bindValue()` and `bindParam()` on the statement it returns. Names match with or without the leading `:`. When the number of values differs from the number of placeholders, the statement has a `placeholder-count-mismatch` finding.

## Receivers

A call is recognised when its receiver is a `PDO` or `PDOStatement`, or a subclass of one. See [receivers](../extensions.md#receivers) for how the class of a receiver is determined.

A subclass declared outside the analyzed paths, for example in `vendor/`, is not known to extend `PDO`, and calls on it are not reported. Add the file that declares it to the analyzed paths.

## Limits

- A statement passed to a function in the analyzed source keeps its bindings, including those made inside the function. After it is passed to a function without a body, such as one in `vendor/`, later bindings are not attached and its placeholders are reported as unbound.
- `bindParam()` binds the value the variable holds at the `bindParam()` call. A later assignment to the variable before `execute()` is not seen.
