# mysqli

The `mysqli` extension catalogs SQL sent through `mysqli` and `mysqli_stmt`, written as methods or as `mysqli_*` functions. It is enabled by default.

```php
function findUser(mysqli $db, int $id): array
{
    $statement = $db->prepare('SELECT * FROM users WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();

    return $statement->get_result()->fetch_all();
}
```

```
SELECT * FROM users WHERE id = ?
? = int
```

## Calls

| Sink ID | Call | Reads |
|---------|------|-------|
| `mysqli.query` | `mysqli::query($sql)` | The SQL. |
| `mysqli.real_query` | `mysqli::real_query($sql)` | The SQL. |
| `mysqli.multi_query` | `mysqli::multi_query($sql)` | The SQL, as one statement. |
| `mysqli.execute_query` | `mysqli::execute_query($sql, $params)` | The SQL and its bound values. |
| `mysqli.prepare` | `mysqli::prepare($sql)` | The SQL. The returned `mysqli_stmt` collects the values bound to it. |
| `mysqli.stmt.bind_param` | `mysqli_stmt::bind_param($types, ...$vars)` | Bound values, in order. The type string is not read. |
| `mysqli.stmt.execute` | `mysqli_stmt::execute($params)` | Bound values. |
| `mysqli.fn.query` | `mysqli_query($link, $sql)` | The SQL. |
| `mysqli.fn.real_query` | `mysqli_real_query($link, $sql)` | The SQL. |
| `mysqli.fn.multi_query` | `mysqli_multi_query($link, $sql)` | The SQL, as one statement. |
| `mysqli.fn.execute_query` | `mysqli_execute_query($link, $sql, $params)` | The SQL and its bound values. |
| `mysqli.fn.prepare` | `mysqli_prepare($link, $sql)` | The SQL. |

A prepared statement is reported once, at the prepare call, with the values of every `bind_param()` and `execute()` on the statement it returns.

## Receivers

A method call is recognised when its receiver is a `mysqli` or `mysqli_stmt`, or a subclass of one declared in the analyzed paths. The `mysqli_*` functions are recognised by name, whatever `$link` is.

## Limits

- `mysqli_stmt_bind_param()` and `mysqli_stmt_execute()` are not read, so a statement prepared with `mysqli_prepare()` and bound with them reports its placeholders as unbound. Use the `mysqli_stmt` methods to have the values catalogued.
- The SQL of `multi_query()` is catalogued as one entry, even when it holds several statements. Its kind is that of the first statement.
- The limits of [PDO](pdo.md#limits) on passing statements to other functions and on bound variables apply here too.
