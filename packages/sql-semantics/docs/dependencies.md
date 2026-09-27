# Dependencies

A statement means something against the statements that came before it. `Semantics::analyze()` takes those statements as its dependencies, in order, and answers a statement whose `resolution` says what it declares and what every table name it writes resolves to. Without dependencies, a statement is structured only and its `resolution` is null.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\ReferenceKind;

$semantics = new Semantics(Dialect::PostgreSql);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$orders = $semantics->analyze('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users (id))', [$users]);
$query = $semantics->analyze('WITH paid AS (SELECT id FROM orders) SELECT name FROM users JOIN paid ON paid.id = users.id', [$users, $orders]);

foreach ($query->resolution->references as $reference) {
    [$reference->name, $reference->kind];
    // [['paid'], ReferenceKind::CommonTableExpression]
    // [['orders'], ReferenceKind::Dependency], with ->declaration === $orders and ->table its declared columns
    // [['users'], ReferenceKind::Dependency]
    // [['paid'], ReferenceKind::CommonTableExpression]
}
$query->resolution->tables(); // the two references to dependencies
```

## Declarations

The dependencies are applied in order. A `CREATE TABLE` declares a table with its columns, types, nullability, defaults, generated columns, collations, constraints, and table options, read as `Statement\Declaration\TableDefinition` and available as `$statement->resolution->declarations` and on each reference as `->table`. A `DROP TABLE` removes an earlier declaration, so a later statement that names the table is an error. `CREATE TABLE IF NOT EXISTS` of a table already declared and `DROP TABLE IF EXISTS` of one not declared change nothing; their references are marked `conditional`. A declaration of a table already declared, an unconditional drop of an unknown table, a duplicate column, or a constraint over an unknown column is a `SemanticException` with a stable `reason`.

A declaration whose columns come from another relation, such as `CREATE TABLE ... LIKE`, `CREATE TABLE ... AS SELECT`, or a partition of another table, declares its name but no readable table: its reference is a `Declaration` with a null `table`, and later references to it resolve with a null `table` too. Views, indexes, and other objects that are not tables are neither declared nor referenced. Computing the columns such statements would produce needs query evaluation and is left to the application, which has the statement model to do it from.

A dependency that was analyzed without dependencies is resolved from its own SQL when it is used, so a plain `analyze('CREATE TABLE ...')` can be passed as a dependency. `analyzeAll()` with dependencies resolves each statement of a script against the dependencies and the statements before it, which is how a script of declarations is read.

## References

Every table name a statement writes is a `Statement\Reference`: the name value as written, so it can be found and rewritten in the statement, its decoded parts with the schema before the table, its kind, and what it resolves to.

| Kind | Meaning |
|------|---------|
| `Dependency` | A table declared by a dependency; `declaration` is that statement and `table` its declared columns |
| `Declaration` | A table the statement itself declares, or names again in its own constraints |
| `CommonTableExpression` | A common table expression the statement defines; a name it shadows resolves to it |
| `Drop` | A table the statement drops; `declaration` is where it was declared |

A name that resolves to none of these is a `SemanticException` with reason `unknown-table`: the dependency that would declare it was not given. Names are compared as the dialect compares relation names, and an unqualified name is read in the dialect's default schema, so in MySQL `db.users` and `users` are different tables.

Table names are found where each grammar writes them: in FROM and JOIN clauses, INSERT, UPDATE, DELETE, and MERGE targets, TRUNCATE, ALTER TABLE, CREATE INDEX, foreign key references, and the sources of `CREATE TABLE ... LIKE` and `... AS SELECT`. Aliases and column names are not resolved, and a name written inside a stored program body is not read.

## Verification

Each database package fuzzes declarations: every `CREATE TABLE` sql-faker generates from the grammar must resolve to one readable table, write back the same SQL, and read the same declaration again, unchanged by an unrelated conditional drop before it. Resolution against dependencies is stated by unit tests for every statement kind above in each dialect.
