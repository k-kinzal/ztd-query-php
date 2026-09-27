# Reading declarations and literals

A DDL reader can inspect a table before its referenced tables are available. Pass an empty dependency list and select `Declarations::Partial` to keep absent dependencies as explicit unresolved references. Declaration validation still runs; duplicate columns and invalid key expressions do not become partial results.

```php
use SqlSemantics\Core\Declarations;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\ReferenceKind;

$semantics = new Semantics(Dialect::MySql);
$statement = $semantics->analyze(
    'CREATE TABLE child (id INT, parent_id INT, FOREIGN KEY (parent_id) REFERENCES parent(id))',
    dependencies: [], declarations: Declarations::Partial,
);
$table = $statement->resolution->declarations[0];
$reference = $statement->resolution->references[1];
$reference->kind === ReferenceKind::Undeclared; // true
$reference->name;                             // ['parent']
$reference->table;                            // null
```

Complete declarations remain the default when dependencies are supplied. Pass the parent declaration and analyze the SQL again to resolve the reference; the previous statement stays unchanged. `analyzeAll()` accepts the same declaration policy and applies preceding statements in order. Partial declarations also records absent table references in queries. Drops keep their `Drop` kind and can have no known table. Nothing unresolved is inserted into the known schema as a fabricated table.

SQLite temporary tables have schema `temp`. Unqualified references search declared tables in `temp` before the configured session path (`main` and any attached databases); explicitly qualified names retain their namespace. All three dialects record foreign table names, including unresolved ones.

## Declared and effective numeric sizes

`TypeDescriptor::precision` and `scale` describe what was written. `effectiveNumericSize` is an immutable `NumericSize` containing the precision and scale enforced for an exact decimal type. Its precision is positive. Its scale can be negative or larger than precision where the dialect permits that. A missing size means no enforced decimal size is known from the declaration.

| Declaration | Declared precision / scale | Effective precision / scale |
|---|---|---|
| MySQL `DECIMAL` | null / null | 10 / 0 |
| MySQL `DECIMAL(0)` | 0 / null | 10 / 0 |
| MySQL `DECIMAL(5)` | 5 / null | 5 / 0 |
| PostgreSQL `NUMERIC(8)` | 8 / null | 8 / 0 |
| PostgreSQL `NUMERIC(2,-3)` | 2 / -3 | 2 / -3 |
| PostgreSQL `NUMERIC` | null / null | no enforced size |
| SQLite `DECIMAL(5)` | 5 / null | no enforced size |

MySQL applies the [documented decimal defaults](https://dev.mysql.com/doc/refman/8.0/en/numeric-type-syntax.html). PostgreSQL distinguishes [constrained and unconstrained numeric types](https://www.postgresql.org/docs/17/datatype-numeric.html). SQLite's declared arguments do not impose a decimal size; the type's affinity remains available separately.

## Reading a type without a table

`Semantics::type()` accepts exactly one column type in the selected grammar and mode. This includes PostgreSQL `format_type()` output. It returns a `TypeDeclaration` containing the descriptor, implied column properties such as serial generation, and the complete typed type syntax in `source`. Attributes, extra columns, and additional statements are rejected.

```php
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

$types = new Semantics(PostgreSqlDialect::PostgreSql);
$declaration = $types->type('numeric(7,2)');
$declaration->type->precision;                    // 7
$declaration->type->effectiveNumericSize->scale;  // 2
```

User-defined type modifiers remain in the typed `source` even when their meaning depends on a database extension. Array dimensions, qualified type names, and interval fields remain structured. Reading a type alone does not supply enclosing table options, such as SQLite's `STRICT` option; read the complete declaration when those affect the type.

## Defaults and literal values

A column's `defaultExpression` preserves its whole DEFAULT clause, including a constraint name. Its `defaultValue` contains the value expression without the clause envelope. `DEFAULT NULL` has a default expression whose decoded value is SQL NULL; no default has neither field. SQLite's bare default identifiers are represented as string expressions, and signed defaults preserve their sign. The original attribute stays in `attributes` and `defaultExpression`. Direct construction of a `ColumnDefinition` must supply both default fields together, or neither; the constructor rejects an incomplete pair.

`Semantics::decodeLiteral()` accepts a typed literal element from a declaration or a builder. It returns an immutable value under `Statement\Literal`:

| Variant | `value()` |
|---|---|
| `StringLiteral` | Decoded string bytes; `characterSet` retains an explicit introducer |
| `BinaryLiteral` | Exact bytes |
| `BitLiteral` | Binary digits, preserving leading zeros and bit width |
| `NumberLiteral` | Exact decimal text, including any fraction or exponent |
| `BooleanLiteral` | PHP `bool` |
| `NullLiteral::Null` | PHP `null` |

```php
$table = $semantics->analyze(
    "CREATE TABLE choices (choice ENUM('a''b', 'second') DEFAULT 'second')",
    dependencies: [], declarations: Declarations::Partial,
)->resolution->declarations[0];

$column = $table->columns[0];
$semantics->decodeLiteral($column->type->members[0])->value(); // "a'b"
$semantics->decodeLiteral($column->defaultValue)->value();     // 'second'
```

Numeric text avoids silently rounding SQL decimals or overflowing a PHP integer. `NumberLiteral::toInt()` performs an explicit checked conversion and throws `RangeException` when the spelling is not a representable integer. A decoder does not apply column casts, collations, character-set transcoding, or floating-point storage conversion.

Strings follow the selected language: MySQL honors `NO_BACKSLASH_ESCAPES`, quote doubling, adjacent strings, and national or character-set introducers. PostgreSQL supports standard-conforming, escape, Unicode (including `UESCAPE` and surrogate pairs), continued, and dollar-quoted strings. Its `X'...'` and `B'...'` values are bit strings. SQLite strings double quotes without interpreting backslashes, and blobs remain bytes. See the [MySQL string rules](https://dev.mysql.com/doc/refman/8.0/en/string-literals.html), [PostgreSQL lexical rules](https://www.postgresql.org/docs/17/sql-syntax-lexical.html), and [SQLite expressions](https://www.sqlite.org/lang_expr.html).

Parentheses and numeric unary signs can surround a literal. Functions, casts, operators, parameters, and column references require more than literal decoding and throw `Core\Literal\DecodingException`. For example, `'x'::text` remains a structured cast expression; it is not guessed to mean a PHP string. Failure to decode is never reported as SQL NULL.

## Validation and reuse

MySQL NULL attributes apply in writing order. `SERIAL DEFAULT VALUE` implies NOT NULL, AUTO_INCREMENT, and UNIQUE, while an AUTO_INCREMENT column without a key is rejected. PostgreSQL rejects conflicting NULL and NOT NULL attributes. SQLite rejects expressions in table PRIMARY KEY and UNIQUE constraints while accepting collated or parenthesized column names. These checks describe declaration semantics; they do not emulate storage engines or execute queries.

Reuse a `Semantics` instance for a DDL directory so its resolved language, parser, and model vocabulary are reused. Returned statements and declaration values remain independent and immutable. An application may cache those values without sharing writable analysis state.
