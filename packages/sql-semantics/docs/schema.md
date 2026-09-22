# Schema

Use `SchemaBuilder` to read table definitions from SQL. It returns a `Schema`
containing the tables, their ordered columns, and declared constraints. Pass that
schema to [Binder](binder.md) when reading queries and other statements.

## Public interface

The following declarations show the public signatures; method bodies are omitted.

```php
namespace SqlSemantics;

final class SchemaBuilder
{
    public readonly Dialect $dialect;
    public readonly string $defaultSchema;

    public function __construct(
        Dialect $dialect,
        ?string $defaultSchema = null,
        ?string $grammarVersion = null,
    ) { /* ... */ }

    public function build(string ...$sql): Schema { /* ... */ }
}
```

Select `Dialect::MySql`, `Dialect::PostgreSql`, or `Dialect::Sqlite`.
`defaultSchema` is the namespace for unqualified table names; its default is an
empty string for MySQL, `public` for PostgreSQL, and `main` for SQLite.
`grammarVersion` selects one of the [documented releases](../README.md#support-syntax).

Each `build()` call starts a new schema and applies the supplied definitions in
order. Pass no SQL to obtain an empty schema. Existing `Schema` objects are not
modified by later calls.

## Read table and column definitions

```php
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE prices (amount NUMERIC(10,2) NOT NULL DEFAULT 0)',
);

$table = $schema->tables[0];
$column = $table->columns[0];

$table->name;                        // prices
$column->name;                       // amount
$column->type->name;                 // numeric
$column->type->modifiers;            // ['10', '2']
$column->nullability->value;         // not-null
$column->defaultExpression;          // Original DEFAULT syntax
```

The result separates the schema's language settings from its table definitions:

```php
namespace SqlSemantics;

use SqlParser\Parser\Node;
use SqlSemantics\Schema\TableDefinition;

final class Schema
{
    public readonly Dialect $dialect;
    public readonly string $grammarVersion;
    public readonly string $defaultSchema;

    /** @var list<TableDefinition> */
    public readonly array $tables;

    /** @var list<Node> Original schema statements, in order. */
    public readonly array $statements;
}
```

The declarations below describe what callers read from a schema. Each table's
`columns` and `constraints` preserve declaration order.

| Object | What to read |
|--------|--------------|
| `TableDefinition` | `schema` and `name` identify the table; `columns` and `constraints` contain its definitions; `source` retains the original declaration. |
| `ColumnDefinition` | `name`, `type`, and `nullability` describe the column. `defaultExpression`, `generatedExpression`, and `attributes` retain the declared expressions and options. |
| `TypeDescriptor` | `name` is the database type; `modifiers` contains precision, scale, length, or other declared modifiers. `dialect` identifies the database and `affinity` records SQLite affinity. |
| `TableConstraint` | `kind` is `PrimaryKey`, `Unique`, `ForeignKey`, or `Check`. `columns` names the local columns; `name` is an optional constraint name. Foreign keys expose `referencedTable` and `referencedColumns`; CHECK exposes `expression`. `source` retains the complete constraint syntax. |
| `Nullability` | `NotNull`, `MaybeNull`, `AlwaysNull`, or `Unknown`. A column's declaration-level fact is independent of NULLs introduced when that table is used in an outer join. |

Defaults, generated values, and CHECK conditions here are syntax nodes. Bind the
CREATE TABLE SQL to obtain their typed expressions and column references through
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

## Invalid definitions

Lexical and syntax errors identify SQL that cannot be read. Invalid declarations
raise `SemanticException`; its `reason` identifies the problem and `source`
identifies the responsible SQL node or token. For example, two columns with the
same name cannot form a valid table definition.
