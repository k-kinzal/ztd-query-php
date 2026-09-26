# Schema State

`SqlSemantics\Facade\Schema` reads the prior state needed to analyze statements. It returns `SqlSemantics\Core\Schema`: a dialect and grammar release, a default namespace, and ordered tables with columns, types and integrity constraints. `SqlSemantics\Facade\Semantics` models operations on that state. A schema is not a collection of arbitrary commands and does not execute SQL.

```php
use SqlSemantics\Facade\Schema;
use SqlSemantics\Core\Binder;
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
(new Binder($schema))->bind('SELECT label FROM items');
```

## Reading declarations

`analyze(string ...$sql)` reads independent input strings in order. Each string can contain several statements. Lexer boundaries and grammar acceptance keep semicolons inside strings, comments and compound statements intact. Calling `analyze()` without arguments gives an empty state; calls never accumulate earlier declarations.

Explicit CREATE TABLE declarations retain their complete typed declaration, column attributes, defaults, generated or identity clauses, collations, integrity constraints, and table options. Type descriptors recognize the binder's built-in types and retain other declared type names and modifiers, including user-defined types. Retaining a type does not imply that the binder implements every operator overload for it.

DROP TABLE applies in declaration order, including multiple targets and IF EXISTS. A subsequent CREATE defines a new table. CREATE TABLE IF NOT EXISTS preserves an existing table. Duplicate unconditional declarations, duplicate columns or primary keys, and unknown local constraint columns are semantic errors.

Schema handles declarations with known columns. CREATE AS SELECT, LIKE/inheritance, ALTER, views and other operations remain typed statement models in `Semantics`; Schema does not evaluate them to invent a resulting catalog. SQLite temporary-schema resolution also remains outside this state reader. The SELECT binder's supported expression and query surface is documented in [binding](binding.md).

SQLite STRICT and WITHOUT ROWID options affect primary-key nullability. In a STRICT table, `ANY` has no coercing affinity (`blob`), whereas an ordinary table gives it numeric affinity; see [STRICT tables](https://www.sqlite.org/stricttables.html). Ordinary SQLite primary keys may remain nullable; the INTEGER PRIMARY KEY rules are applied separately. See [SQLite CREATE TABLE](https://www.sqlite.org/lang_createtable.html), [PostgreSQL CREATE TABLE](https://www.postgresql.org/docs/17/sql-createtable.html), and [MySQL CREATE TABLE](https://dev.mysql.com/doc/refman/8.4/en/create-table.html) for database definitions.

## Structured values and invariants

All state objects are final and expose readonly fields. The `source`, default, CHECK, collation, generation and option fields are independent `Statement\Element` values from the selected dialect's typed model. They contain neither parser nodes nor retained SQL strings. `Writer::render()` reconstructs a declaration or fragment from these fields.

Column generation uses `ColumnGeneration` and `GenerationKind` (`virtual`, `stored`, `identity`). Computed columns carry an expression; identity columns carry identity options and no computation expression. Column attributes remain available as typed values even when a property is outside the SELECT binder's supported surface.

Constructors check collection member types, ordered lists, immutable SQL graphs, generation shape, constraint shape, consistent column dialects and unique table identities. Invalid direct construction throws `InvalidArgumentException`, including when PHP assertions are disabled. Invalid SQL syntax throws `AnalysisException`; conflicting or unresolved declarations throw `SemanticException` with a reason code.

`$state->withTables(...$tables)` returns a new state with the same dialect, version and default namespace. `$column->withNullability($fact)` returns a refined column while sharing its immutable declaration data. Neither method changes the original value. Statement-model fields have their own typed `with*()` methods; analyze a changed declaration again when its catalog facts must be recomputed.

## Migrating from SchemaBuilder

`Core\SchemaBuilder` remains as a deprecated compatibility adapter. Replace `new SchemaBuilder($dialect, $defaultSchema, $grammarVersion)` with `new Facade\Schema(...)`, and `build(...)` with `analyze(...)`. The new entry point reports syntax errors as `AnalysisException`, consistently with `Semantics`; the adapter preserves the previous parser exception contract.

Consumers of state `source` or expression fields should replace parser traversal with the typed model fields and replace `Node::toString()` with `Writer::render()`. SELECT binding results still have their existing source-node contract.

## Fuzzing state and statements

Each database package provides two complementary properties:

- `composer fuzz:roundtrip`: every statement generated from the grammar's external statement entry point must survive `Semantics` analysis and SQL reconstruction.
- `composer fuzz:schema`: sql-faker generation plans produce explicit prior-state declarations. Every generated declaration must survive `Schema` analysis, preserve all declaration structure, reconstruct the same state, contain no parser objects, and remain unchanged by unrelated conditional DROP resets.

The state plans use a single column and one column-attribute slot to avoid accidental duplicate identities or repeated primary keys. Types, expressions, attributes and table options remain grammar-generated. Inheritance and query-derived columns belong to statement analysis; they are deliberately outside the state plan, not caught and discarded after generation. Neither property allows exceptions or filters failing generated inputs. These bounded campaigns test the properties; they do not constitute an exhaustive proof of SQL or server equivalence.

```sh
# In a database package
composer fuzz:smoke
MYSQL_VERSION=5.6.51 composer fuzz:schema -- --max-runs=100
MYSQL_VERSION=9.1.0 composer fuzz:schema -- --max-runs=100
```

The byte corpus is reproducible, grammar coverage is recorded separately, and CI runs both properties. `MYSQL_VERSION` selects a shipped MySQL release. The grammar's internal parser selectors are not SQL statements and are excluded by choosing the external statement entry point.
