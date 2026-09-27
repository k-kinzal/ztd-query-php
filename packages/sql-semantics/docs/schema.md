# Schema State

`SqlSemantics\Facade\Schema` reads declarations into the state statements are analyzed against. It returns `SqlSemantics\Core\Schema`: a dialect and grammar release, a default namespace, and ordered tables with columns, types and integrity constraints. The state is what the declarations say, not the result of running them: `SqlSemantics\Facade\Semantics` models the operations, including the ones that would change state, and this reader does not evaluate them. A schema is not a collection of arbitrary commands and does not execute SQL.

```php
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Writer;

$schema = (new Schema(Dialect::MySql))->analyze(<<<'SQL'
DROP TABLE IF EXISTS items;
CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(80) COLLATE utf8mb4_bin,
    label_length BIGINT GENERATED ALWAYS AS (LENGTH(label)) STORED
) ENGINE=InnoDB;
SQL);

$column = $schema->tables[0]->columns[2];
$column->generation->kind->value;                  // 'stored'
Writer::render($column->generation->expression); // 'LENGTH ( label )'
```

`Schema` takes the dialect, an optional default namespace, an optional release tag, and the mode and parameter syntax `Semantics` takes, for example `new Schema(Dialect::MySql, null, 'mysql-8.0.44', Mode::fromString('ANSI_QUOTES'))`.

## Reading declarations

`analyze(string ...$sql)` reads independent input strings in order. Each string can contain several statements. Lexer boundaries and grammar acceptance keep semicolons inside strings, comments and compound statements intact. Calling `analyze()` without arguments gives an empty state; calls never accumulate earlier declarations.

Explicit CREATE TABLE declarations retain their complete typed declaration, column attributes, defaults, generated or identity clauses, collations, integrity constraints, and table options. A type is a `TypeDescriptor`: a `Builtin` case or a `TypeName` for a user-defined or unmodeled type, with its length, precision, scale, sign, character set, array and affinity facts as separate typed fields; see [types](#types). Recognizing a type does not imply that the binder implements every operator overload for it. A declaration whose type arguments the database cannot store, such as an overflowing length, an expression as a type modifier, or a set-returning column type, is a semantic error.

DROP TABLE applies in declaration order, including multiple targets and IF EXISTS. A subsequent CREATE defines a new table. CREATE TABLE IF NOT EXISTS preserves an existing table. Duplicate unconditional declarations, duplicate columns or primary keys, and unknown local constraint columns are semantic errors.

Schema handles declarations with known columns. CREATE AS SELECT, LIKE/inheritance, ALTER, views and other operations remain typed statement models in `Semantics`, which structures every one of them and can walk and rewrite them; Schema does not evaluate them to invent a resulting catalog. Computing the state after such an operation is the work of the application that applies it, from the statement model and the prior state. SQLite temporary-schema resolution also remains outside this state reader.

SQLite STRICT and WITHOUT ROWID options affect primary-key nullability. In a STRICT table, `ANY` has no coercing affinity (`Affinity::Blob`), whereas an ordinary table gives it numeric affinity; see [STRICT tables](https://www.sqlite.org/stricttables.html). Ordinary SQLite primary keys may remain nullable; a sole primary key column is the rowid alias, and NOT NULL, only when its type is spelled exactly `INTEGER`, so `INT PRIMARY KEY` and `INTEGER(5) PRIMARY KEY` stay nullable. SQLite ignores the numeric arguments of a type; integer arguments are kept as the length, or precision and scale, the standard spelling means, and other arguments stay only in the typed declaration. See [SQLite CREATE TABLE](https://www.sqlite.org/lang_createtable.html), [PostgreSQL CREATE TABLE](https://www.postgresql.org/docs/17/sql-createtable.html), and [MySQL CREATE TABLE](https://dev.mysql.com/doc/refman/8.4/en/create-table.html) for database definitions.

## Types

`TypeDescriptor` separates the identity of a type from the facts declared around it. The identity is a `Builtin` case, spelled canonically, or a `TypeName` for a user-defined, domain, or unmodeled type, whose decoded name parts are kept. Each dialect maps its synonyms onto the same cases: `INT1`, `MIDDLEINT`, `INT8` and `FLOAT8` become `Builtin::TinyInt`, `MediumInt`, `BigInt` and `DoublePrecision`; PostgreSQL catalog names such as `int4`, `timestamptz` and `varbit` resolve the same way as their keyword spellings; SQLite maps the names its documentation lists and keeps other names, such as `UNSIGNED BIG INT`, as a `TypeName` with their affinity.

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

A `TypeDescriptor` is always a valid state: a `Builtin` must be one the dialect supports, `zerofill` implies `unsigned`, a scale requires a precision, only numeric types carry a sign, only character types carry a character set, and exactly the enumeration types have members. Invalid construction throws `InvalidArgumentException`. `is()` compares the identity alone, so `VARCHAR(10)` and `VARCHAR(20)` are the same type with different lengths. Strings appear only for what a declaration chooses freely: names, character sets, and the typed literals of enumeration members.

Type spellings that stand for column properties report them separately as a `TypeDeclaration`, which the column declaration applies: MySQL `SERIAL` declares an unsigned `BigInt` that is `autoIncrement`, NOT NULL and unique; PostgreSQL `SERIAL`, `BIGSERIAL` and `SMALLSERIAL` declare an `autoIncrement`, NOT NULL integer. A MySQL column attribute `KEY` declares the primary key. Two cases are not declared types: `Builtin::Dynamic` is a SQLite column without a declared type, whose storage class follows each value, and `Builtin::Any` is the SQLite declared type that accepts every storage class.

## Structured values and invariants

All state objects are final and expose readonly fields. The `source`, default, CHECK, collation, generation and option fields are independent `Statement\Element` values from the selected dialect's typed model. They contain neither parser nodes nor retained SQL strings. `Writer::render()` reconstructs a declaration or fragment from these fields.

Column generation uses `ColumnGeneration` and `GenerationKind` (`virtual`, `stored`, `identity`). Computed columns carry an expression; identity columns carry identity options and no computation expression. Column attributes remain available as typed values, and the `source` of a declaration is its complete statement model.

Constructors check collection member types, ordered lists, immutable SQL graphs, generation shape, constraint shape, consistent column dialects and unique table identities. Invalid direct construction throws `InvalidArgumentException`, including when PHP assertions are disabled. Invalid SQL syntax throws `AnalysisException`; conflicting or unresolved declarations throw `SemanticException` with a reason code.

`$state->withTables(...$tables)` returns a new state with the same dialect, version and default namespace. `$column->withNullability($fact)` returns a refined column while sharing its immutable declaration data. Neither method changes the original value. Statement-model fields have their own typed `with*()` methods; analyze a changed declaration again when its catalog facts must be recomputed.

## Fuzzing state and statements

Each database package provides two complementary properties:

- `composer fuzz:roundtrip`: every statement generated from the grammar's external statement entry point must survive `Semantics` analysis and SQL reconstruction.
- `composer fuzz:schema`: sql-faker generation plans produce explicit prior-state declarations. Every generated declaration must survive `Schema` analysis, preserve all declaration structure, reconstruct the same state, contain no parser objects, and remain unchanged by unrelated conditional DROP resets.

The state plans use a single column and one column-attribute slot to avoid accidental duplicate identities or repeated primary keys, and they keep type arguments to the forms the database accepts: MySQL lengths that fit 64 bits, PostgreSQL types without expression modifiers and without SETOF. Types, expressions, attributes and table options otherwise remain grammar-generated. Inheritance and query-derived columns belong to statement analysis; they are deliberately outside the state plan, not caught and discarded after generation. Neither property allows exceptions or filters failing generated inputs. These bounded campaigns test the properties; they do not constitute an exhaustive proof of SQL or server equivalence.

```sh
# In a database package
composer fuzz:smoke
MYSQL_VERSION=5.6.51 composer fuzz:schema -- --max-runs=100
MYSQL_VERSION=9.1.0 composer fuzz:schema -- --max-runs=100
```

Schema fuzzing starts from an empty or restored corpus; sql-semantics packages do not ship seeds. The evolving byte corpus is reproducible, grammar coverage is recorded separately, and CI runs both properties. `MYSQL_VERSION` selects a shipped MySQL release. The grammar's internal parser selectors are not SQL statements and are excluded by choosing the external statement entry point.
