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
| `unionAll($left, $right)` | The rows of both queries |
| `cte($name, $query, $columns)`, `with($ctes, $query)` | Common table expressions and the query they precede |

The database packages narrow the return types to their roles: a MySQL condition is an `ExprForm`, a PostgreSQL one an `AExprForm`. A query given to `unionAll`, `cte`, or `with` may be the `command` of an analyzed statement; its terminator envelope is removed, and the result is again a complete command that `new Statement($query)` writes.

## Spelling

A name is written bare when the release reads it back as the same name, and quoted otherwise: MySQL quotes reserved words and names its lexer would read as numbers; PostgreSQL also quotes a name with an upper-case letter, which the server would fold; SQLite quotes every keyword, because whether the parser falls back to reading a keyword as a name depends on where it stands. Whether a bare word is a keyword, and which keywords a name role admits, is read from the release's own keyword table and grammar, so `action` is bare in MySQL and `"user"` is quoted in PostgreSQL.

A string is escaped as the session reads escapes. MySQL doubles backslashes unless the mode has `NO_BACKSLASH_ESCAPES`; PostgreSQL strings are standard-conforming; a byte no literal of the language can hold, such as NUL in PostgreSQL or SQLite, is a `CompositionException`. Numbers are spelled so the lexer gives them the width the server would, and a negative number is the unary minus applied to a literal, as the server parses it.

## Precedence

An operand that binds more weakly than the position it is placed in is parenthesized: `compare(and(a, b), '=', c)` writes `( a AND b ) = c`, and `and(a, or(b, c))` writes `a AND( b OR c )`. The decision uses the binding powers the generated model records from each grammar's precedence declarations, so it follows the release. Under MySQL's `HIGH_NOT_PRECEDENCE`, `NOT` binds as tightly as `!` and `not(compare(a, '=', b))` writes `NOT( a = 1 )` where the default mode writes `NOT a = 1`; under `PIPES_AS_CONCAT`, `or` still writes the word.

## Releases

A value the release has no form for is a `CompositionException`: MySQL 5.6 and 5.7 have no common table expressions, and their `UNION` is a chain the builder appends to, parenthesizing a SELECT with its own ORDER BY or LIMIT. SQLite writes a compound left to right, so the right operand of `unionAll` is one SELECT.

## Verification

Every value the builder composes must survive `Semantics::analyze()` and write the same SQL, and the value found in the analyzed statement must equal the composed one. The unit tests of each database package state this for names, literals, conditions, set operations, and common table expressions, in each MySQL release whose query grammar differs. The builder is built on the same vocabulary the round-trip fuzzing exercises, and it names no generated class.
