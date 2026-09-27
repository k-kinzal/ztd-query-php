# Composition

`SqlSemantics\Core\Builder` composes the values every dialect has, under stable names, from PHP data and from other values. `Semantics::builder()` answers the builder of the selected language, so a name, a literal, or a condition is spelled as that release reads it under that mode.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Statement\Writer;

$semantics = new Semantics(Dialect::MySql, mode: Mode::fromString('NO_BACKSLASH_ESCAPES'));
$builder = $semantics->builder();

$condition = $builder->and(
    $builder->compare($builder->column('select'), '=', $builder->string('C:\path')),
    $builder->not($builder->or($builder->column('a'), $builder->column('b'))),
);
Writer::render($condition); // "`select` = 'C:\path' AND NOT( a OR b )"
```

## What is composed

| Method | Value |
|--------|-------|
| `identifier($name)` | A name in the role that column, table, and alias names share |
| `column(...$parts)`, `table(...$parts)` | A reference, qualified by the names before the last |
| `string($value)`, `integer($value)`, `float($value)`, `boolean($value)`, `null()`, `binary($bytes)` | A literal holding exactly the PHP value |
| `parameter($marker)` | A bound parameter marker: numbered where the language numbers them, or the named placeholder `:name` where the language reads it |
| `and`, `or`, `not`, `compare($left, $operator, $right)`, `parenthesized` | Conditions; `compare` takes `=`, `<>`, `!=`, `<`, `<=`, `>`, `>=` and a dialect's own spellings such as MySQL's `<=>` |
| `isNull($operand, $negated)`, `in($operand, $values, $negated)` | `IS [NOT] NULL` and `[NOT] IN (...)` |
| `case($whens, $else)` | A searched CASE of conditions and their results |
| `call($name, $arguments)` | A function call; a name the release reads as a keyword, such as MySQL's `COALESCE`, takes the form the grammar gives it |
| `cast($operand, $type)` | `CAST` to a `Statement\Declaration\TypeDescriptor`, spelled as the release's CAST names it |
| `select($columns, $from, $where)` | A SELECT of expressions with their aliases, from an optional table, filtered by an optional condition |
| `unionAll($left, $right)` | The rows of both queries |
| `cte($name, $query, $columns)`, `with($ctes, $query)` | Common table expressions and the query they precede |

The database packages narrow the return types to their roles: a MySQL condition is an `ExprForm`, a PostgreSQL one an `AExprForm`. A query given to `unionAll`, `cte`, or `with` may be the `command` of an analyzed statement; its terminator envelope is removed, and the result is again a complete command that `new Statement($query)` writes.

Together they compose the rows of a table from its declared types, without SQL text:

```php
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Element;

$semantics = new Semantics(Dialect::MySql);
$builder = $semantics->builder();
$users = $semantics->analyze('CREATE TABLE users (id BIGINT, price DECIMAL(10,2))', []);
$columns = $users->resolution->declarations[0]->columns;

$row = static fn (array $values): Element => $builder->select(array_map(
    static fn (ColumnDefinition $column, Element $value): array => [$builder->cast($value, $column->type), $column->name],
    $columns,
    $values,
));
$rows = $builder->unionAll($row([$builder->integer(1), $builder->string('9.50')]), $row([$builder->integer(2), $builder->null()]));
Writer::render($builder->with([$builder->cte('users', $rows)], $semantics->analyze('SELECT id FROM users')->command));
// 'WITH users AS( SELECT CAST( 1 AS SIGNED ) AS id , CAST( \'9.50\' AS DECIMAL( 10 , 2 ) ) AS price UNION ALL SELECT CAST( 2 AS SIGNED ) AS id , CAST( NULL AS DECIMAL( 10 , 2 ) ) AS price ) SELECT id FROM users'
```

A query of no rows keeps its column types: `select($columns, where: $builder->boolean(false))`.

## Spelling

A name is written bare when the release reads it back as the same name, and quoted otherwise: MySQL quotes reserved words and names its lexer would read as numbers; PostgreSQL also quotes a name with an upper-case letter, which the server would fold; SQLite quotes every keyword, because whether the parser falls back to reading a keyword as a name depends on where it stands. Whether a bare word is a keyword, and which keywords a name role admits, is read from the release's own keyword table and grammar, so `action` is bare in MySQL and `"user"` is quoted in PostgreSQL.

A string is escaped as the session reads escapes. MySQL doubles backslashes unless the mode has `NO_BACKSLASH_ESCAPES`; PostgreSQL strings are standard-conforming; a byte no literal of the language can hold, such as NUL in PostgreSQL or SQLite, is a `CompositionException`. Numbers are spelled so the lexer gives them the width the server would, and a negative number is the unary minus applied to a literal, as the server parses it. A float is spelled with the fewest digits that read back as the same double, whatever the `precision` and `serialize_precision` settings, and negative zero is written negated, which keeps its sign where the literal's type has one: PostgreSQL's `numeric` has no negative zero. MySQL reads a number with an exponent as an approximate-value `DOUBLE` and one without as an exact `DECIMAL`, so the MySQL builder always writes the exponent: `float(0.1 + 0.2)` is `3.0000000000000004e-1`. PostgreSQL reads a non-integer literal as `numeric` and SQLite as `REAL`, and both builders write `0.30000000000000004`; a cast to `double precision` reads the PostgreSQL literal back unchanged.

## Casts

`cast()` names exactly the type it is given, never a nearby one, and a type the release's CAST cannot name, or a fact of it the target cannot state, is a `CompositionException`:

| Database | CAST names |
|----------|------------|
| MySQL | `CHAR` for a `VARCHAR`, with its length, character set, and binary collation, or without a length for one as long as the value; `BINARY` for a `VARBINARY` as long as the value; `SIGNED` and `UNSIGNED` for a `BIGINT`; `DECIMAL`; `DOUBLE`; `FLOAT`; `DATE`, `TIME`, `DATETIME` with a precision, and `YEAR`; `JSON`; the spatial types. No target names `INT`, `CHAR`, `BINARY`, `TEXT`, or `ENUM`: `CAST(... AS CHAR(n))` gives a `VARCHAR(n)`, and `AS BINARY(n)` pads the value to n bytes, which no declared type does. A display width or `ZEROFILL` cannot be stated |
| PostgreSQL | Every type of PostgreSQL and every named type, with its length, precision and scale, time precision, interval fields, and array dimensions; a sign or a character set cannot be stated |
| SQLite | Every type name, with its length or precision and scale. The value takes the affinity of the name, so a type whose affinity differs from its name's, such as `ANY` in a STRICT table, and a column without a declared type are errors |

A target a release lacks is an error of that release: MySQL reads `CAST(... AS FLOAT)` from 8.0.17 and `AS YEAR` from 8.0.22.

## Precedence

An operand that binds more weakly than the position it is placed in is parenthesized: `compare(and(a, b), '=', c)` writes `( a AND b ) = c`, and `and(a, or(b, c))` writes `a AND( b OR c )`. The decision uses the binding powers the generated model records from each grammar's precedence declarations, so it follows the release. Under MySQL's `HIGH_NOT_PRECEDENCE`, `NOT` binds as tightly as `!` and `not(compare(a, '=', b))` writes `NOT( a = 1 )` where the default mode writes `NOT a = 1`; under `PIPES_AS_CONCAT`, `or` still writes the word.

## Releases

A value the release has no form for is a `CompositionException`: MySQL 5.6 and 5.7 have no common table expressions, and their `UNION` is a chain the builder appends to, parenthesizing a SELECT with its own ORDER BY or LIMIT. SQLite writes a compound left to right, so the right operand of `unionAll` is one SELECT.

## Forms

`isNull()`, `in()`, `case()`, `call()`, `cast()`, and `select()` take their forms from the release itself: each writes SQL with placeholders where its operands go, reads it with the release's parser, and puts the operands in place of the placeholders, parenthesized where they bind too weakly. The form is therefore the one the server reads for that SQL, and a form the release lacks is a `CompositionException`, such as an empty `IN ()` outside SQLite, a MySQL `ROW_NUMBER()` without its `OVER` clause, or a SELECT with WHERE but no FROM before MySQL 8.0.

## Verification

Every value the builder composes must survive `Semantics::analyze()` and write the same SQL, and the value found in the analyzed statement must equal the composed one. Float spellings were read back by MySQL 8.4, PostgreSQL 17, and SQLite 3 for thousands of random doubles, each to the same bits, and rows composed with `select()`, `cast()`, `case()`, `in()`, `isNull()`, and `call()` ran on those servers with the values and column types they state. The unit tests of each database package state this for names, literals, conditions, set operations, and common table expressions, in each MySQL release whose query grammar differs. The builder is built on the same vocabulary the round-trip fuzzing exercises, and it names no generated class.
