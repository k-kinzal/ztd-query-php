# Schema

`SchemaBuilder` converts ordered schema SQL into an immutable `Schema` snapshot.
Consumers such as fixture generators can inspect declared columns, types,
defaults, generated values, and integrity constraints without connecting to a
database. Use [Binder](binder.md) to bind expressions and statements against that
snapshot.

## Public interface

The entry points are in the `SqlSemantics` namespace.

| Interface | Result and behavior |
|-----------|---------------------|
| `new SchemaBuilder(Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null)` | Selects the language, default namespace, and parser release. The namespace defaults to `public` for PostgreSQL, `main` for SQLite, and an empty database name for MySQL. See the [release tables](../README.md#support-syntax). |
| `SchemaBuilder::build(string ...$sql): Schema` | Parses zero or more SQL strings and applies their schema statements in order. Each call starts a new snapshot; arguments do not extend a previous call's result. No arguments returns an empty catalog. |
| `Schema::$dialect: Dialect` | `Dialect::MySql`, `Dialect::PostgreSql`, or `Dialect::Sqlite`. |
| `Schema::$grammarVersion: string` | Resolved release tag, including when the default was selected. The binder uses the same release. |
| `Schema::$defaultSchema: string` | Namespace for unqualified declarations and references. |
| `Schema::$tables: list<TableDefinition>` | Visible table and view declarations after applying the statements. |
| `Schema::$statements: list<Node>` | Original schema statements in order, including auxiliary objects and options retained as syntax. `Node` is `SqlParser\Parser\Node`. |

Syntax and lexical errors propagate from sql-parser. Declaration conflicts and
unresolvable declarations raise `SemanticException`, whose `reason` and `source`
identify the failure. Schema construction has no diagnostic-collecting equivalent
of `Binder::analyze()`.

## Returned declarations

Declaration classes are in `SqlSemantics\Schema`; type classes are in
`SqlSemantics\Type`. Collections preserve declaration order.

| Object | Public properties | Meaning |
|--------|-------------------|---------|
| `TableDefinition` | `schema`, `name`, `columns`, `constraints`, `source`, `resolved` | Qualified identity, ordered columns and integrity constraints, and original declaration syntax. `resolved` distinguishes a known declaration from an unresolved relation retained during analysis. |
| `ColumnDefinition` | `name`, `type`, `nullability`, `source`, `defaultExpression`, `attributes`, `generatedExpression` | Declared column facts. Defaults and generated expressions are parser nodes; `attributes` retains attributes such as identity and collation. |
| `TypeDescriptor` | `dialect`, `name`, `modifiers`, `affinity` | Canonical database type, declared modifiers such as precision/scale, and SQLite affinity where applicable. |
| `Nullability` | `NotNull`, `MaybeNull`, `AlwaysNull`, `Unknown` | NULL facts exposed through enum cases; backed values are `not-null`, `maybe-null`, `always-null`, and `unknown`. |
| `TableConstraint` | `kind`, `columns`, `source`, `name`, `referencedTable`, `referencedColumns`, `expression` | Primary/unique keys, foreign references, and CHECK conditions. Foreign-key actions and deferrability remain in `source`; CHECK syntax is also available as `expression`. |
| `ConstraintKind` | `PrimaryKey`, `Unique`, `ForeignKey`, `Check` | Declared integrity category. |

Declaration expressions contain syntax. For typed expressions and bound column
references, bind the CREATE TABLE statement and inspect
`BoundStatement::$definitions`; see [declaration binding](binder.md#declarations-and-nested-commands).

## SQL and returned structures

Unless a dialect is specified, these examples use `Dialect::PostgreSql` and the
default namespace. Each row is an independent `build()` call. Multiple statements
in a row are applied in the shown order. For MySQL, pass each statement as a
separate argument, as shown for CREATE TABLE LIKE. Paths below are relative to the returned
`Schema`, and show selected fields rather than a serialized object format.

| SQL passed to `build()` | Returned structure |
|------------------------|--------------------|
| `CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER DEFAULT 0)` | `tables[0].name = 'users'`; `schema = 'public'`; columns are `id`, `score`; `id.type.name = 'integer'`; `id.nullability = NotNull`; `score.defaultExpression` retains `0`; a `PrimaryKey` constraint has `columns = ['id']`. |
| `CREATE TABLE prices (amount NUMERIC(10,2) NOT NULL)` | `tables[0].columns[0].type` has `name = 'numeric'`, `modifiers = ['10', '2']`; `nullability = NotNull`. |
| `CREATE TABLE totals (amount INTEGER, doubled INTEGER GENERATED ALWAYS AS (amount * 2) STORED, CHECK (amount >= 0))` | `columns[1].generatedExpression` retains `amount * 2`; a `Check` constraint retains the condition in `expression`. |
| `CREATE TABLE parents (id INTEGER PRIMARY KEY); CREATE TABLE children (parent_id INTEGER REFERENCES parents(id) ON DELETE CASCADE)` | Two declarations; the child's `ForeignKey` constraint has `columns = ['parent_id']`, `referencedTable = ['parents']`, `referencedColumns = ['id']`; `source` retains `ON DELETE CASCADE`. |
| `CREATE TABLE source (id INTEGER); CREATE TABLE copied AS SELECT id FROM source` | `tables[1].name = 'copied'`; its ordered columns derive from the SELECT outputs, including `id` with type `integer`. |
| `CREATE TABLE source (id INTEGER); CREATE VIEW ids AS SELECT id FROM source` | A declaration named `ids` exposes the query's ordered output columns and types; its source retains the view definition. |
| MySQL, two arguments: `CREATE TABLE source (id INT NOT NULL)`, `CREATE TABLE copied LIKE source` | `copied` has the copied column declaration, including `id` and its `NotNull` fact. |
| `CREATE TABLE users (id INTEGER); ALTER TABLE users ADD COLUMN score INTEGER; ALTER TABLE users RENAME TO accounts` | The final declaration is `accounts` with ordered columns `id`, `score`; `statements` retains all three schema operations. |
| `CREATE TABLE users (id INTEGER); DROP TABLE users` | `tables = []`; `statements` retains CREATE and DROP. |
| SQLite: `CREATE TABLE prices (amount NUMERIC(10,2))` | The column retains its declared numeric type and modifiers; `type.affinity = 'numeric'`. Affinity does not promise a runtime storage class. |
| `CREATE TABLE users (id INTEGER); CREATE INDEX users_id ON users(id)` | `users` remains available in `tables`; the index statement is retained in `statements`, rather than represented as another table. |

## Example

```php
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(
    Dialect::PostgreSql,
    defaultSchema: 'app',
    grammarVersion: 'pg-17.2',
))->build('CREATE TABLE prices (amount NUMERIC(10,2) NOT NULL DEFAULT 0)');

$table = $schema->tables[0];
$column = $table->columns[0];
$table->schema;                    // app
$column->type->name;               // numeric
$column->type->modifiers;          // ['10', '2']
$column->nullability->value;       // not-null
$column->defaultExpression;        // Original default syntax node
```

The snapshot supplies declarations, not generated fixture rows or a live server
catalog. See [limitations](limitation.md) for inference and execution boundaries.
