# Inspecting SQLite SQL

The parser extracts the statement structure needed by ZTD. It is not a replacement for SQLite's syntax checker: recognizing a statement does not prove that SQLite will accept or execute it.

## Classification and splitting

```php
require 'vendor/autoload.php';

$parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
$parser->classifyStatement('WITH ids AS (SELECT 1) SELECT * FROM ids'); // 'SELECT'
$parser->splitStatements("SELECT ';'; SELECT 2"); // ["SELECT ';'", 'SELECT 2']
$parser->extractTargetTable('INSERT INTO users (id) VALUES (1)'); // 'users'
```

`SqliteQueryGuard` translates recognized statements into the core `QueryKind` enum:

```php
$guard = new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard($parser);
$guard->classify('SELECT * FROM users'); // QueryKind::READ
$guard->classify('INSERT INTO users (id) VALUES (1)'); // QueryKind::WRITE_SIMULATED
$guard->classify('CREATE TABLE users(id INTEGER)'); // QueryKind::DDL_SIMULATED
$guard->classify('VACUUM'); // null

try {
    $guard->assertAllowed('VACUUM');
} catch (\RuntimeException $exception) {
    // Unsupported or unsafe SQL is rejected before execution.
}
```

Transaction commands use the session's `transactionStatement()` API. They are not DML classifications. Some read-only diagnostics and in-memory attachments have explicit passthrough handling; see the [SQLite specification](../../../docs/sqlite-spec.md) for those boundaries.

## Extracting clauses

These methods return SQL fragments, not evaluated values or bound parameters:

| Purpose | `SqliteParser` methods |
|---|---|
| Statements and tables | `classifyStatement`, `splitStatements`, `extractTargetTable`, `extractSelectTables` |
| INSERT columns and values | `extractInsertColumns`, `extractInsertValues`, `hasInsertSelect`, `extractInsertSelect` |
| Conflict handling | `hasOnConflict`, `isReplace`, `isInsertIgnore`, `extractOnConflictUpdates`, `extractOnConflictUpdateWhere` |
| UPDATE expressions | `extractUpdateAssignments`, `extractUpdateAlias`, `extractUpdateFromClause` |
| Filtering and ordering | `extractWhereClause`, `extractOrderByClause`, `extractLimitClause` |
| Lexical inspection | `stripComments`, `maskStringLiterals`, `unquoteIdentifier` |

Each method's generated API page includes an executable example, including its exact return shape. Missing optional clauses return `null` or an empty collection according to that method's contract.

```php
$parser->extractInsertValues('INSERT INTO users VALUES (1, 2 + 3)'); // [['1', '2 + 3']]
$parser->extractUpdateAssignments('UPDATE users SET score = score + 1'); // ['score' => 'score + 1']
$parser->extractWhereClause('DELETE FROM users WHERE id = 1'); // 'id = 1'
```

## Schema definitions

Parse DDL into the core `TableDefinition` value used by the rewrite pipeline:

```php
$schema = (new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser())->parse(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)',
);
$schema?->columns; // ['id', 'name']
$schema?->primaryKeys; // ['id']
```

`SqliteSchemaReflector` accepts a core `ConnectionInterface`. `getCreateStatement($tableName)` returns a table's DDL or `null`; `reflectAll()` returns table names mapped to DDL; `reflectViews()` returns view definitions. Temporary schemas take precedence over persistent schemas with the same name. `SqliteSessionFactory` performs this reflection when it creates a session.

## Missing-schema errors

Pass a core `DatabaseException`, including the driver's error code and message:

```php
$classifier = new \ZtdQuery\Platform\Sqlite\SqliteErrorClassifier();
$error = new \ZtdQuery\Connection\Exception\DatabaseException('no such table: users', 1);
$classifier->isUnknownSchemaError($error); // true

$syntaxError = new \ZtdQuery\Connection\Exception\DatabaseException('near SELECT: syntax error', 1);
$classifier->isUnknownSchemaError($syntaxError); // false
```

SQLite uses code `1` for several errors. The classifier also inspects the message; the code alone is insufficient.
