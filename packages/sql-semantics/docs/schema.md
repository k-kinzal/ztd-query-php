# Schema

Use `SchemaBuilder` to read table definitions from SQL. It returns a `Schema`
containing the tables, their ordered columns, indexes, options, and declared constraints. Pass that
schema to [Binder](binder.md) when reading queries and other statements.

## Public interface

| Entry point | Responsibility |
|-------------|----------------|
| `new SchemaBuilder(Dialect $dialect, ?string $defaultSchema = null, ?string $grammarVersion = null, array $functions = [])` | Select the database language and registered function signatures. |
| `SchemaBuilder::build(string ...$sql): Schema` | Apply schema declarations in order and return a new snapshot. |
| `Schema::withFunctions(FunctionSignature ...$functions): Schema` | Return a new snapshot with added or replaced function overloads. |

Select `Dialect::MySql`, `Dialect::PostgreSql`, or `Dialect::Sqlite`.
`defaultSchema` is the namespace for unqualified table names; its default is an
empty string for MySQL, `public` for PostgreSQL, and `main` for SQLite.
`grammarVersion` selects one of the [documented releases](../README.md#support-syntax).

Each `build()` call starts a new schema and applies the supplied definitions in
order. Pass no SQL to obtain an empty schema. Existing `Schema` objects are not
modified by later calls.

## Read table and column definitions

`Schema` exposes `dialect`, `grammarVersion`, `defaultSchema`, ordered `tables`,
registered `functions`, and original schema `statements`. A snapshot is immutable.
SchemaBuilder applies definition changes in input order; Binder interprets statements
against the snapshot it receives. This separates definition evolution from reading the
meaning of an operation.

The following objects describe what callers read from a schema. Each table's
`columns` and `constraints` preserve declaration order.

| Object | What to read |
|--------|--------------|
| `TableDefinition` | `schema` and `name` identify the table; `columns`, `constraints`, and `indexes` contain its definitions; `options` contains named table options; `source` retains the original declaration. |
| `ColumnDefinition` | `name`, `type`, and `nullability` describe the column. `defaultExpression` and `generatedExpression` retain declared expressions. `options` contains named column options; `attributes` retains their original syntax. |
| `TypeDescriptor` | `name` is the database type; `modifiers` contains precision, scale, length, or other declared modifiers. `dialect` identifies the database and `affinity` records SQLite affinity. |
| `TableConstraint` | `kind` is `PrimaryKey`, `Unique`, `ForeignKey`, or `Check`. `columns` names the local columns; `name` is an optional constraint name. Foreign keys expose `referencedTable`, `referencedColumns`, `onDelete`, `onUpdate`, `match`, and `deleteColumns`. `deferrable` and `initiallyDeferred` describe checking time; CHECK exposes `expression`. `source` retains the complete constraint syntax. |
| `IndexDefinition` | `schema`, `name`, and `table` identify the index and its destination; `elements` preserves key order; `unique`, `method`, `include`, `predicate`, and `options` describe its behavior. An omitted index name is `null`. |
| `IndexElement` | `column` or `expression` identifies the key; `direction`, `nulls`, `collation`, `operatorClass`, `prefixLength`, and `options` retain its modifiers. |
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
| `CREATE TABLE parents (id INTEGER PRIMARY KEY); CREATE TABLE children (parent_id INTEGER REFERENCES parents(id) ON DELETE CASCADE)` | Two declarations; the child's `ForeignKey` constraint has `columns = ['parent_id']`, `referencedTable = ['parents']`, `referencedColumns = ['id']`; `onDelete = ReferentialAction::Cascade`. |
| `CREATE TABLE source (id INTEGER); CREATE TABLE copied AS SELECT id FROM source` | `tables[1].name = 'copied'`; its ordered columns derive from the SELECT outputs, including `id` with type `integer`. |
| `CREATE TABLE source (id INTEGER); CREATE VIEW ids AS SELECT id FROM source` | A declaration named `ids` exposes the query's ordered output columns and types; its source retains the view definition. |
| MySQL, two arguments: `CREATE TABLE source (id INT NOT NULL)`, `CREATE TABLE copied LIKE source` | `copied` has the copied column declaration, including `id` and its `NotNull` fact. |
| `CREATE TABLE users (id INTEGER); ALTER TABLE users ADD COLUMN score INTEGER; ALTER TABLE users RENAME TO accounts` | The final declaration is `accounts` with ordered columns `id`, `score`; `statements` retains all three schema operations. |
| `CREATE TABLE users (id INTEGER); DROP TABLE users` | `tables = []`; `statements` retains CREATE and DROP. |
| SQLite: `CREATE TABLE prices (amount NUMERIC(10,2))` | The column retains its declared numeric type and modifiers; `type.affinity = 'numeric'`. Affinity does not promise a runtime storage class. |
| `CREATE TABLE users (id INTEGER); CREATE INDEX users_id ON users(id)` | `tables[0].indexes[0]` has `name = 'users_id'`, `table = ['public', 'users']`, and an element with `column = 'id'`; `statements` also retains the original declaration. |

| Additional SQL | Returned structure |
|----------------|--------------------|
| `CREATE TABLE users (id INTEGER, score INTEGER); CREATE UNIQUE INDEX active_scores ON users(score DESC) INCLUDE(id) WHERE score > 0` | `indexes[0].unique = true`; its first element has `column = 'score'`, `direction = 'DESC'`; `include = ['id']`; `predicate` retains `score > 0`. |
| `CREATE TABLE users (id INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 10 INCREMENT BY 2))` | `columns[0].options = ['identity' => 'always', 'start' => '10', 'increment' => '2']`. |
| MySQL: `CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, KEY names(name(10))) ENGINE=InnoDB` | `options['engine'] = 'InnoDB'`; `id.options['auto_increment'] = true`; `name.options` exposes `character_set` and `collation`; the index key has `prefixLength = 10`. |
| SQLite: `CREATE TABLE users (id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT` | `options = ['without_rowid' => true, 'strict' => true]`. |

Unquoted option names use lowercase words separated by underscores; qualified
storage parameters retain their dot, and quoted names retain their case. Flag values are
booleans; other values are decoded strings or ordered string lists. Numeric
spellings remain strings, so declaration precision is preserved. These maps
contain explicit declarations, not values fetched from a running server.

## Register function signatures

Register application functions through `Schema::withFunctions()` on the schema used
for binding. Construct each `FunctionSignature` with the following fields:

| Field | Meaning |
|-------|---------|
| `name`, optional `schema` | Resolved function name and namespace, without SQL quoting. |
| `parameters` | Ordered `TypeDescriptor` argument types; an empty list means no arguments, `null` means unspecified. |
| `returnType` | A `TypeDescriptor` or deterministic closure from argument types to a result descriptor. |
| `nullability` | Result NULL fact for non-NULL arguments; defaults to `Unknown`. |
| `nullOnNull` | Whether NULL input propagates to the result; defaults to false. |
| `variadic`, `optionalParameters` | Repeated final parameter and number of optional trailing positions. |
| `aggregate` | Whether the function is an aggregate. |

To use signatures while deriving tables or views from SELECTs, supply them to
`new SchemaBuilder($dialect, functions: [$signature])`. They remain available in
the returned schema. The default functions are registered as `FunctionSignature`
objects through the same mechanism; `Schema::$functions` exposes the overloads.

`parameters: []` declares no arguments; `null` leaves argument types and arity
unspecified. A variadic signature repeats its final parameter. `optionalParameters`
counts optional trailing positions, including the variadic position when it may
be omitted. `returnType` can be a deterministic callback over argument types for
polymorphic functions. It describes a type; it does not execute the SQL function.
`nullability` describes the result for non-NULL arguments, and `nullOnNull` adds
NULL propagation from arguments. `aggregate: true` marks an aggregate function.

Names are resolved identifiers without SQL quotes. PostgreSQL unquoted names are
lowercase; a quoted name retains its case. `schema` qualifies a function; an
unqualified call can use the default namespace or an unqualified signature.
Registering the same name, namespace, and parameter types replaces that overload;
different parameter types add overloads. Exact argument types take precedence
over implicit conversions and unspecified signatures. Missing, incompatible, or
ambiguous arguments to a registered function produce diagnostics, or exceptions
in strict mode. An unregistered function remains structurally available with an
unknown result type. COALESCE and NULLIF retain their conditional-expression
semantics.

## Invalid definitions

Lexical and syntax errors identify SQL that cannot be read. Invalid declarations
raise `SemanticException`; its `reason` identifies the problem and `source`
identifies the responsible SQL node or token. For example, two columns with the
same name cannot form a valid table definition.
