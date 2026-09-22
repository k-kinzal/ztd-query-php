# Schema

`Schema` describes the state against which SQL is bound: table and column
identities, declared types, integrity conditions, function signatures, and variable
declarations. It contains no table rows or runtime variable values. `SchemaBuilder`
reads definition SQL into an immutable snapshot; [Binder](binder.md) interprets an
operation against that snapshot.

## Public interface

```php
new SchemaBuilder(
    Dialect $dialect,
    ?string $defaultSchema = null,
    ?string $grammarVersion = null,
    array $functions = [],
);
SchemaBuilder::build(string ...$sql): Schema;
Schema::withFunctions(FunctionSignature ...$functions): Schema;
Schema::withVariables(VariableDefinition ...$variables): Schema;
```

Select `Dialect::MySql`, `Dialect::PostgreSql`, or `Dialect::Sqlite` and a
[grammar release](../README.md#support-syntax). The default namespace is the empty
string for MySQL, `public` for PostgreSQL, and `main` for SQLite. Each `build()` call
starts a new snapshot and applies its supplied definitions in order. Calling
`Binder::bind()` does not apply the bound operation to that snapshot.

`Schema` exposes `dialect`, `grammarVersion`, `defaultSchema`, ordered `tables`,
registered `functions`, `variables`, and original schema `statements`. The `source`
fields retain diagnostic provenance. Defaults, computations, predicates, and index
expressions are semantic expression objects, with types and resolved references.

## Table and column structures

| Structure | Information returned |
|-----------|----------------------|
| `TableDefinition` | Resolved `schema` and `name`, ordered `columns`, `constraints`, `indexes`, and dialect-specific `properties`. |
| `ColumnDefinition` | `name`, `TypeDescriptor type`, declaration-level `Nullability nullability`, a concrete `generation`, and typed `attributes`. |
| `SuppliedColumn` | Optional insertion `default` and `onUpdate` expressions. An omitted default is `null`, distinct from an explicit SQL NULL literal. |
| `ComputedColumn` | Required `expression` and `GeneratedStorage storage`. |
| `IdentityColumn` | Required `IdentityMode mode` and `SequenceOptions sequence`, including declared start, increment, bounds, cache and cycle. |
| `AutoIncrementColumn` | A value supplied by the MySQL table's auto-increment mechanism. |
| `Column\Attributes` | Named properties such as `collation`, `characterSet`, `comment`, `visible`, `storage`, `format`, and `compression`; absent declarations are `null`. |
| `MySqlProperties` | Engine, character set, collation, row format and the other named MySQL table storage properties. |
| `PostgreSqlProperties` | Persistence, commit behavior, access method, tablespace and ordered storage parameters. |
| `SqliteProperties` | `withoutRowId`, `strict`, and `temporary`. |

Column generation alternatives have different required properties. A computed
column cannot carry an insertion default in place of its required expression.
The expression describes the computation; reading a declaration does not execute it.
`Nullability` is `NotNull`, `MaybeNull`, `AlwaysNull`, or `Unknown`. Query binding
creates separate expression facts for a column's use, including NULL extension
introduced by an outer join.

## Declared types

`TypeDescriptor` exposes `dialect`, `identity`, a canonical `name`, and SQLite
`affinity` when applicable. The concrete identity determines which parameters exist.
Numeric parameter spellings remain strings to preserve their precision.

| Identity | Required and optional information |
|----------|-----------------------------------|
| `BuiltinIdentity` | A finite built-in type identity, such as integer, boolean, text, or an unknown result type. |
| `IntegerStorage` | Integer family, optional display width, and unsigned policy. |
| `NumericStorage` | Numeric family, optional precision and scale, and unsigned policy; scale requires precision. |
| `StringStorage` | String or binary family, optional length, character set, and binary policy. |
| `TemporalStorage` | Temporal family, optional precision, and time-zone mode. |
| `IntervalStorage` | Interval fields and optional precision. |
| `Enumeration` / `LabelSet` | An ordered nonempty list of declared string labels. |
| `ArrayStorage` | Element `TypeDescriptor` and a nonempty list of declared dimensions. |
| `NamedIdentity` | Qualified name of a PostgreSQL type and its modifier expressions. |
| `SqliteDeclaration` | Declared name, storage affinity, and optional size and scale. Affinity describes conversion rules, not the runtime class of a stored value. |

## Integrity conditions and indexes

| Structure | Information returned |
|-----------|----------------------|
| `PrimaryKey` / `UniqueKey` | Nonempty ordered `keys` and `CheckingTime checking`; each key has a concrete column or expression form. |
| `ForeignKey` | Nonempty local `columns`, required qualified `referencedTable`, optional explicit `referencedColumns`, `onDelete`, `onUpdate`, `match`, `checking`, and affected `deleteColumns` for a SET action. |
| `Check` | Required typed `predicate`, enforcement and inheritance policy. |
| `IndexDefinition` | Index identity, target table, ordered `elements`, uniqueness, access method, included columns, typed partial-index `predicate`, and storage `properties`. |
| `ColumnKey` | A required column reference and optional prefix length. |
| `ExpressionKey` | A required expression. |
| `IndexElement` | Common key ordering, NULL ordering, collation, operator class, and its declared parameters. |

Constraints also carry an optional declared `name`. A foreign key with explicit
referenced columns requires matching local and referenced widths. SQLite MATCH
syntax does not change matching behavior, so its semantic matching mode is Simple.

## SQL and returned structures

Unless labeled otherwise, examples use PostgreSQL and its default namespace.
Paths show selected fields of the returned `Schema`; these are object properties,
not a serialized interchange format. Multiple input statements are applied in order.

| SQL passed to `build()` | Returned structure |
|------------------------|--------------------|
| `CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER DEFAULT 0)` | `tables[0]` is `users` in `public`; ordered columns are `id` and `score`; `id` is NotNull; `score.generation` is `SuppliedColumn` with a literal `default`; the `PrimaryKey` contains the resolved `id` key. |
| `CREATE TABLE prices (amount NUMERIC(10,2) NOT NULL)` | The column's `type.identity` is `NumericStorage`, with precision spelling `10` and scale spelling `2`; its nullability is NotNull. |
| `CREATE TABLE totals (amount INTEGER, doubled INTEGER GENERATED ALWAYS AS (amount * 2) STORED, CHECK (amount >= 0))` | `doubled.generation` is `ComputedColumn` with a multiplication expression and Stored storage; the `Check` has a comparison predicate referring to `amount`. |
| `CREATE TABLE children (parent_id INTEGER REFERENCES parents(id) ON DELETE CASCADE)` | The `ForeignKey` names local `parent_id`, referenced table `parents`, referenced column `id`, and `ReferentialAction::Cascade`. |
| `CREATE TABLE users (id INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 10 INCREMENT BY 2))` | `id.generation` is `IdentityColumn`, mode Always; its sequence contains literal start `10` and increment `2`. |
| `CREATE TABLE source (id INTEGER); CREATE TABLE copied AS SELECT id FROM source` | The `copied` declaration derives its ordered columns and types from the query outputs. |
| `CREATE TABLE source (id INTEGER); CREATE VIEW ids AS SELECT id FROM source` | The `ids` relation exposes the query's output column `id` and its type. |
| `CREATE TABLE users (id INTEGER); ALTER TABLE users ADD COLUMN score INTEGER; ALTER TABLE users RENAME TO accounts` | The final declaration is `accounts`, with ordered columns `id` and `score`. Original schema `statements` retain the three operations. |
| `CREATE TABLE users (id INTEGER); DROP TABLE users` | The resulting `tables` list is empty. |
| `CREATE TABLE users (id INTEGER, score INTEGER); CREATE UNIQUE INDEX active_scores ON users(score DESC) INCLUDE(id) WHERE score > 0` | The index is unique; its first element is a descending `ColumnKey` for `score`; `include` contains `id`; `predicate` is the typed comparison `score > 0`. |
| MySQL: `CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin, KEY names(name(10))) ENGINE=InnoDB` | `properties.engine` is InnoDB; `id.generation` is `AutoIncrementColumn`; `name.attributes` supplies the character set and collation; its index key has prefix length 10. |
| MySQL, separate input arguments: `CREATE TABLE source (id INT NOT NULL)`, `CREATE TABLE copied LIKE source` | The copied declaration contains `id` and its NotNull fact. |
| SQLite: `CREATE TABLE prices (amount NUMERIC(10,2))` | The identity is `SqliteDeclaration`, with numeric affinity and size/scale spellings `10` and `2`. |
| SQLite: `CREATE TABLE users (id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT` | `properties` is `SqliteProperties`, with `withoutRowId` and `strict` both true. |

## Variable declarations

`VariableDefinition` contains a name, `VariableScope`, `TypeDescriptor`, and
`Nullability`. `withVariables()` returns a new snapshot with these declarations.
A bound variable reference identifies the declaration and its scope; it never
contains a fetched or evaluated runtime value. Assignments describe which expression
would be written to the variable without changing the input snapshot.

## Register function signatures

Register application functions through `Schema::withFunctions()` on the schema used
for binding. Construct each `FunctionSignature` with the following fields:

| Field | Meaning |
|-------|---------|
| `name`, optional `schema` | Resolved function name and namespace, without SQL quoting. |
| `parameters` | Ordered `TypeDescriptor` argument types; an empty list means no arguments, `null` means unspecified. |
| `returnType` | A `TypeDescriptor` or deterministic closure from argument types to a result descriptor. |
| `nullability` | Fixed `Nullability`, or a deterministic closure from ordered argument `Nullability` facts to a result fact; defaults to `Unknown`. |
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
`nullability` supplies the result fact, either directly or from argument NULL facts;
`nullOnNull` adds strict NULL propagation. These callbacks receive static facts, never
runtime values. `aggregate: true` marks an aggregate function.

Names are resolved identifiers without SQL quotes. PostgreSQL unquoted names are
lowercase; a quoted name retains its case. `schema` qualifies a function; an
unqualified call can use the default namespace or an unqualified signature.
Registering the same name, namespace, and parameter types replaces that overload;
different parameter types add overloads. Exact argument types take precedence
over implicit conversions and unspecified signatures. Incompatible types or ambiguous
overloads produce diagnostics, or exceptions in strict mode. An impossible argument
count raises `InvalidSql`, including in non-strict mode. An unregistered function
retains its explicit name and argument structure with an unknown result type.

MySQL and SQLite COALESCE, IFNULL, and NULLIF use registered signatures, including
argument-dependent NULL rules. PostgreSQL COALESCE, NULLIF, GREATEST, and LEAST are
language operations with concrete expression classes. A qualified ordinary function
such as `app.coalesce(...)` still resolves through the supplied signatures.

## Invalid definitions

Lexical and syntax errors identify SQL that cannot be read. Invalid declarations
raise `SemanticException`; its `reason` identifies the problem and `source`
identifies the responsible SQL node or token. For example, two columns with the
same name cannot form a valid table definition.
