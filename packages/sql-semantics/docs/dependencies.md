# Dependencies

A statement means something against the statements that came before it. `Semantics::analyze()` takes those statements as its dependencies, in order, and answers a statement whose `resolution` says what it declares and what every table name it writes resolves to. Without dependencies, a statement is structured only and its `resolution` is null. Pass an empty dependency list to request resolution against no preceding declarations. [Partial declaration reading](declarations.md) preserves absent dependencies as explicit unresolved references.

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

A dependency that was analyzed without dependencies is resolved from its own SQL, against the dependencies before it, when it is used, so a plain `analyze('CREATE TABLE ...')` can be passed as a dependency, and its foreign keys refer to the tables declared before it. `analyzeAll()` with dependencies resolves each statement of a script against the dependencies and the statements before it, which is how a script of declarations is read.

## Types

A column's `type` is a `Statement\Declaration\TypeDescriptor`, which separates the identity of a type from the facts declared around it. The identity is a `Builtin` case, spelled canonically, or a `TypeName` for a user-defined, domain, or unmodeled type, whose decoded name parts are kept. Each dialect maps its synonyms onto the same cases: `INT1`, `MIDDLEINT`, `INT8` and `FLOAT8` become `Builtin::TinyInt`, `MediumInt`, `BigInt` and `DoublePrecision`; PostgreSQL catalog names such as `int4`, `timestamptz` and `varbit` resolve the same way as their keyword spellings; SQLite maps the names its documentation lists and keeps other names, such as `UNSIGNED BIG INT`, as a `TypeName` with their affinity.

The other declared facts are independent typed fields, never part of the name:

| Fact | Field | Example |
|------|-------|---------|
| Length or display width | `length` (int) | `VARCHAR(10)`, `INT(11)`, `BIT(8)` |
| Precision and scale | `precision`, `scale` (int) | `NUMERIC(2,-3)`, `TIME(6)`, `FLOAT(24)` |
| Sign and padding | `unsigned`, `zerofill` (bool) | `INT UNSIGNED ZEROFILL` |
| Character set and collation | `characterSet` (string), `binaryCollation` (bool) | `VARCHAR(10) CHARSET utf8mb4 BINARY` |
| Enumeration members | `members` (typed literals) | `ENUM('a','b')` |
| Array dimensions | `arrayDimensions` (int) | `INTEGER[][]` |
| Interval fields | `intervalFields` (`IntervalFields`) | `INTERVAL DAY TO SECOND(3)` |
| Storage affinity | `affinity` (`Affinity`) | SQLite `VARCHAR(20)` has `Affinity::Text` |

A `TypeDescriptor` is always a valid state: `zerofill` implies `unsigned`, a scale requires a precision, only numeric types carry a sign, only character types carry a character set, and exactly the enumeration types have members. Invalid construction throws `InvalidArgumentException`. A dialect only reads the `Builtin` cases it supports. `is()` compares the identity alone, so `VARCHAR(10)` and `VARCHAR(20)` are the same type with different lengths. Strings appear only for what a declaration chooses freely: names, character sets, and the typed literals of enumeration members.

Type spellings that stand for column properties set those properties on the column: MySQL `SERIAL` declares an unsigned `BigInt` column that is `autoIncrement`, NOT NULL and unique; PostgreSQL `SERIAL`, `BIGSERIAL` and `SMALLSERIAL` declare an `autoIncrement`, NOT NULL integer column. A MySQL column attribute `KEY` declares the primary key. `Builtin::Dynamic` is a SQLite column without a declared type, whose storage class follows each value, and `Builtin::Any` is the SQLite declared type that accepts every storage class; in a STRICT table `ANY` has `Affinity::Blob`.

A SQLite primary key column is the rowid alias, and NOT NULL, only when it is the sole key column and its type is spelled exactly `INTEGER`, so `INT PRIMARY KEY` and `INTEGER(5) PRIMARY KEY` stay nullable. SQLite ignores the numeric arguments of a type; integer arguments are kept as the length, or precision and scale, the standard spelling means. In MySQL and PostgreSQL, type arguments the database cannot store, such as an overflowing length, an expression as a type modifier, or a set-returning column type, are a `SemanticException`.

## References

Every table name a statement writes is a `Statement\Reference`: the name value as written, so it can be found and rewritten in the statement, its decoded parts with the schema before the table, its kind, and what it resolves to. The value is the one in the statement itself, and `Traversal::rewrite()` gives the function that same value while nothing below it is replaced, so a rewrite can change exactly the names that resolve to tables by identity; see [traversal](statements.md#traversal). SQLite's FROM writes a qualified name as two values of one form, a schema and a `.name`, with no single value for the whole name; `values` lists every value that writes a name, and `value` is the first.

| Kind | Meaning |
|------|---------|
| `Dependency` | A table declared by a dependency; `declaration` is that statement and `table` its declared columns |
| `Declaration` | A table the statement itself declares, or names again in its own constraints |
| `CommonTableExpression` | A common table expression the statement defines and the name can see; see [common table expressions](#common-table-expressions) |
| `Drop` | A table the statement drops; `declaration` is where it was declared |
| `Undeclared` | A table no dependency declares, under [partial declarations](#partial-declarations); nothing is known of its columns |

A name that resolves to none of these is a `SemanticException` with reason `unknown-table`: the dependency that would declare it was not given. Names are compared as the dialect compares relation names, and an unqualified name is read in the schemas of the [search path](#search-path).

The references are listed in writing order. Table names are found where each grammar writes them: in FROM and JOIN clauses, INSERT, UPDATE, DELETE, and MERGE targets, TRUNCATE, ALTER TABLE, CREATE INDEX, foreign key references, and the sources of `CREATE TABLE ... LIKE` and `... AS SELECT`. Aliases and column names are not resolved, and a name written inside a stored program body is not read.

## Search path

A server reads a table name without a schema in the schemas of its session: MySQL in the current database, PostgreSQL in the schemas of `search_path`, and SQLite in `main` and then the attached databases. Declared SQLite temporary tables in `temp` take precedence over this path. Pass them to `Semantics` as a `Core\SearchPath`, as the server stores their names. An unqualified name refers to the table of the first schema that has one, and an unqualified declaration creates its table in the first schema:

```php
use SqlSemantics\Core\SearchPath;

$semantics = new Semantics(Dialect::MySql, searchPath: new SearchPath('app'));
$users = $semantics->analyze('CREATE TABLE users (id INT PRIMARY KEY)');
$query = $semantics->analyze('SELECT * FROM users JOIN app.users AS again USING (id)', [$users]);

count($query->resolution->tables()); // 2: users and app.users are the same table
```

MySQL has one current database, so its search path has one schema; without one, an unqualified name is read in an unnamed database of its own, and `app.users` is a different table. PostgreSQL searches `public` by default, and SQLite `main`, which a SQLite search path starts with. `Semantics::searchPath()` answers the schemas in use.

## Partial declarations

The dependencies usually describe only some of the tables of a database. Analyzed with `Core\Declarations::Partial`, a name no dependency declares is not an error: it is a reference of kind `Undeclared`, with no declaration and no table, and every other name resolves as before.

```php
use SqlSemantics\Core\Declarations;

$query = $semantics->analyze('SELECT * FROM users JOIN audit_log USING (id)', [$users], Declarations::Partial);
// users is a Dependency with its declared table, audit_log is Undeclared
```

Under partial declarations, dropping an undeclared table is a `Drop` without a declaration. A table the dependencies dropped is known not to exist, so naming it is still an `unknown-table` error until a later dependency declares it again. Declarations that conflict with a dependency are errors either way.

## Common table expressions

A name resolves to a common table expression only where the server can see it. A WITH clause makes its expressions visible in the query or statement it belongs to, subqueries at any depth included, and not outside it; an inner clause shadows an outer one. Inside the clause, the body of each expression sees the expressions each database allows, and a name it cannot see resolves outside the clause, to an outer expression or to a table:

| Database | Plain WITH | WITH RECURSIVE |
|----------|------------|----------------|
| MySQL | The expressions before it | The expressions before it and itself |
| PostgreSQL | The expressions before it | Every expression of the clause |
| SQLite | Every expression of the clause | Every expression of the clause |

So in `WITH users AS (SELECT * FROM users WHERE id > 1) SELECT * FROM users`, MySQL and PostgreSQL read the table `users` inside the expression, and SQLite reads the expression itself, which the server reports as a circular reference:

```php
$semantics = new Semantics(Dialect::PostgreSql);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$query = $semantics->analyze('WITH users AS (SELECT * FROM users WHERE id > 1) SELECT * FROM users', [$users]);

array_map(static fn (Reference $reference): ReferenceKind => $reference->kind, $query->resolution->references);
// [CommonTableExpression, Dependency, CommonTableExpression]
```

The table an INSERT, UPDATE, DELETE, or MERGE writes to is always a table in PostgreSQL and SQLite, even when an expression of that name is visible. In MySQL the target of an UPDATE or DELETE is resolved like any other name, so it can be an expression, which the server then rejects as not updatable. A qualified name is never an expression, and expression names are compared as the database compares table names.

## Verification

Each database package fuzzes declarations: every `CREATE TABLE` sql-faker generates from the grammar must resolve to one readable table, write back the same SQL, and read the same declaration again, unchanged by an unrelated conditional drop before it. Resolution against dependencies is stated by unit tests for every statement kind above in each dialect. The visibility of common table expressions was read from MySQL 8.4, PostgreSQL 17, and SQLite 3 running each case against real tables, and the unit tests state those outcomes.

The [declaration and literal APIs](declarations.md) preserve declared precision and scale separately from effective numeric size, expose default values without their DEFAULT envelope, and decode literal values without evaluating expressions.
