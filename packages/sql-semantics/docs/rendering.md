# Rendering

`Operation::toString()` answers SQL rendered from the statement structure. It never answers the analyzed text, and the model keeps no copy of it. The text is produced once, when the operation is constructed, and is checked before the operation is returned. Written text is kept in one place only, a [spelled region](#spelled-regions): the expression of a result column that the database names after its text.

## How the text is produced

Each structure class writes itself to a typed, temporary output, clause by clause in grammar order. It can write only four kinds of pieces:

| Piece | Content |
|-------|---------|
| Keyword | One fixed upper-case keyword. |
| Symbol | Punctuation or an operator made of symbol characters. |
| Name | A decoded name, spelled by the name codec of the database for its position (column, relation, qualifier, alias, routine, label). |
| Literal | The spelling a literal or parameter class computes from its exact value. |

There is no piece for a free SQL fragment, so no expression assembled as a string can reach the output, and the source text reaches it only as the checked spelling of a spelled region. The pieces are joined with one space, except before `,` `)` `]` `;` `.` and after `(` `[` `.`, and where a class requests no space. The writer does not reorder, optimize, drop or add clauses, replace an alias by its expression, or re-associate operators; parentheses that group an expression are part of the structure and are written back.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze("select a,b from t where (a+1)*2 > b -- comment\n order by a")->toString(); // => 'SELECT a, b FROM t WHERE (a + 1) * 2 > b ORDER BY a'
$semantics->analyze('SELECT 1 + (2 * 3)')->toString(); // => 'SELECT 1 + (2 * 3)'
```

The result is deterministic: the same structure always gives the same text. It is not a formatter; use [SQL Formatter](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-formatter/README.md) for layout.

Only an operation has `toString()`. A part of a structure, such as an expression or a correlated subquery, is not a statement root, and its rendering would not be a safe SQL fragment for every position, so it is not offered.

### Names

Names are written bare when the database reads the bare spelling as the same name at that position, and quoted otherwise. A name is never quoted blindly: the quoting rules differ per database.

| Database | Quoted with | Notes |
|----------|-------------|-------|
| MySQL | backticks | A name that is a keyword, function name or introducer of the release is quoted. Backticks are identifiers under every `sql_mode`, so a double-quoted identifier read under `ANSI_QUOTES` is rendered with backticks. |
| PostgreSQL | double quotes | A name with upper-case letters is quoted, because an unquoted name would be folded to lower case. |
| SQLite | backticks | Backticks, unlike double quotes, never fall back to a string literal. |

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(PostgreSql::PostgreSql))->analyze('SELECT "Name", "name", Name FROM "Users"')->toString(); // => 'SELECT "Name", name, name FROM "Users"'
(new Semantics(Sqlite::Sqlite))->analyze('SELECT a AS [weird name] FROM `select`')->toString(); // => 'SELECT a AS `weird name` FROM `select`'
```

### Literals

Literals hold their exact value: digits as strings, decoded text, hexadecimal digits. They are never converted through a PHP float. A literal is written in a spelling of that value that the database reads back as the same value.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(Sqlite::Sqlite))->analyze("VALUES (1_000, 0X1f, 1.50, X'ab', 'it''s')")->toString(); // => "VALUES (1000, 0x1F, 1.50, x'AB', 'it''s')"
(new Semantics(PostgreSql::PostgreSql))->analyze("SELECT \$\$x\$\$, U&'d\\0061t'")->toString(); // => "SELECT 'x', 'dat'"
```

## Checks before publication

An operation is returned only after the following checks pass. They run on every analysis and every construction, independently of PHP assertion settings. A failure throws `InvariantViolation` (or `InvalidConstruction` for an input outside the closed value domain), and the candidate is discarded; it is never returned in a degraded form.

Every construction, `new Operation(...)` and `analyze()` alike:

1. **Value audit.** Every value reachable from the structure, and from the facts, is an enum case or an instance of a final class of the closed semantic namespaces that uses the `SqlSemantics\Statement\Snapshot` trait (which refuses cloning, dynamic properties and serialization), with initialised readonly properties only, holding no float, closure, resource or PHP reference. A node may occur at one position only.
2. **Fact completeness.** Every expression, relation and query of the structure received exactly one fact. A rule cannot silently skip a region.
3. **Spellings.** When the structure holds [layouts](#spelled-regions), the text as the layouts spell it and the text as the structure renders it without them are read by the lexer of the profile, and must have the same tokens: as many, and each written token an equivalent spelling of the rendered one.
4. **Structure equivalence.** The rendered text is parsed with the same grammar release and lowered by the same rules, and the result must equal the structure operand by operand: the same classes, the same scalar values and enum cases, layouts included, and lists of the same length and order, recursively. This compares actual operands, not a fingerprint or text.

`analyze()` additionally checks the model against the input:

5. **Leaf embedding.** Every operand leaf lowered from the input (identifiers, literals, parameter markers, operators) must be reachable, by object identity, in the published structure. A rule cannot read an operand and then drop it.
6. **Token correspondence.** The significant tokens of the input and of the rendered text must be equal, in order. Tokens are compared by key: keywords and punctuation by their terminal, names by their decoded value, literals by their exact decoded value. Whitespace and comments are not tokens.

Checks 4 and 6 together tie the input, the structure and the rendered text to each other: the rendered text has the same structure as the model, and carries the same significant tokens as the input.

### Noise tables

Some tokens have no influence on meaning in the production they appear in: `WORK` or `TRANSACTION` after `BEGIN`, a statement terminator, in PostgreSQL an optional `AS` before an alias. Each database package lists such positions in noise tables (the classes of the `Rules\Noise` namespace in the MySQL and PostgreSQL packages, `Rules\Noise` and `Rules\DefinitionNoise` in the SQLite package), as pairs of a grammar production and a token position, each with a one-line reason and a citation of the database manual. Only these positions are skipped by the token correspondence check. A token that changes meaning must not be listed; if the writer cannot reproduce a significant token, the model is missing a distinction, and the fix belongs in the model. The tables are kept short so that every entry can be reviewed against its citation, and unit tests pin their content.

Some keywords are declared synonyms: different spellings that the database reads as the same request, such as MySQL `&&` and `AND`, or SQLite `TEMPORARY` and `TEMP`. They are compared as one key, listed in the same tables with the same kind of justification.

## What the rendered SQL does not preserve

The rendered text is SQL that requests the same thing as the input, in the spelling the writer chooses. Outside [spelled regions](#spelled-regions), the following are not preserved:

- whitespace and line breaks;
- comments; MySQL version comments are read as the selected release reads them, and the rendered text writes that reading without the comment markers;
- the letter case of keywords, which are written in upper case;
- optional noise words listed in the noise tables, and statement terminators;
- the spelling of declared synonyms, for example MySQL `VALUE` and `VALUES`, `CONVERT(a, CHAR)` and `CAST(a AS CHAR)`, `KEY` and `INDEX`; SQLite `TEMPORARY` and `TEMP`;
- the spelling of a name or a literal where the database reads several spellings as the same value: identifier quotes, string escapes, dollar quoting and Unicode escapes, digit separators, the case of hexadecimal digits.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;

(new Semantics(MySql::MySql))->analyze('select a from t where a = 1 && b = 2 # comment')->toString(); // => 'SELECT a FROM t WHERE a = 1 AND b = 2'
(new Semantics(MySql::MySql))->analyze('INSERT INTO t VALUE (1)')->toString(); // => 'INSERT INTO t VALUES (1)'
(new Semantics(PostgreSql::PostgreSql))->analyze('BEGIN WORK')->toString(); // => 'BEGIN'
(new Semantics(Sqlite::Sqlite))->analyze('create temporary table x (y)')->toString(); // => 'CREATE TEMP TABLE x (y)'
```

Where a spelling carries meaning, the model keeps the distinction as a bounded value and renders it. In SQLite, for example, `=` and `==` are kept apart, `<>` and `!=` too, and a double-quoted word stays a double-quoted word, because SQLite reads it as a string when no column has that name; the bare words `TRUE` and `FALSE` stay boolean words for the same reason. In SQLite and MySQL, whether `AS` introduces an alias is kept too, because the text these databases name a result column after can include it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT a FROM t WHERE a == 1 AND a != 2')->toString(); // => 'SELECT a FROM t WHERE a == 1 AND a != 2'
$semantics->analyze('select "a" from t')->toString(); // => 'SELECT "a" FROM t'
```

The rendered text targets the selected release and profile. It is not translated to another release or database, and text rendered under one MySQL mode is meant to be read under the same mode.

## Spelled regions

SQLite and MySQL name a result column without an alias after the text of its expression as it was written: `SELECT 1+1` returns a column named `1+1`, and `SELECT 1 + 1` one named `1 + 1`. There the spelling is part of what the statement returns, and rendering the expression in the canonical spelling would rename the column. The model therefore keeps the written spelling of such an expression as a layout (rule `CORE-SPELLING-001`). PostgreSQL names these columns by rules that do not depend on the spelling, such as `?column?` or the name of a called function, so the PostgreSQL package keeps no layouts.

A `SqlSemantics\Statement\Spelling\Layout` holds one `Spelled` value per token the expression renders, in order: `gap`, the whitespace and comments written before the token, and `text`, its written spelling. `trail` holds what was written after the last token; only SQLite uses it, because the SQLite name extends to the start of the next token, so a comment after the expression is part of the name. `text()` answers the text from the first to the last token. A layout is kept only where it differs from the canonical spelling, and an aliased result column keeps none; the database packages document which result columns keep one (`ResultColumn::$layout` in SQLite, `SelectExpression::$layout` in MySQL).

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$query = $semantics->analyze('select 1+1 /* sum */, 2 as two from t where a+1 > 2');
$layout = $query->statement->columns[0]->layout;

$layout->text(); // => '1+1'
$layout->trail; // => ' /* sum */'
$query->statement->columns[1]->layout; // => null
$query->field(0)->name?->value; // => '1+1 /* sum */'
$query->toString(); // => 'SELECT 1+1 /* sum */, 2 AS two FROM t WHERE a + 1 > 2'
```

A layout never stands for structure. The expression is still the structure: facts are derived from it, and it is what the rendering writes. A layout only chooses, for each token the expression renders, an equivalent spelling and the trivia before it; it cannot add, drop or change a token, and nothing is rendered from a layout alone. Inside a spelled region a nested layout has no effect. Apart from the trivia and the token spellings within the region, the rendered text is canonical, as described above.

A layout is checked like any other operand:

- The result column constructors refuse a layout they cannot hold with `InvalidConstruction`: one given together with an alias, in MySQL one with trailing trivia, and in SQLite one without one token per rendered token, with whitespace alone after the expression, or in the canonical spelling.
- Before publication, the text as the layout spells it is compared token by token with the text the structure renders, using the lexer of the profile: every written token must be an equivalent spelling of the rendered token, such as another letter case of a keyword, and the gaps can hold only whitespace and comments.
- The spelled text is then parsed and lowered again, and must give the same structure, layout included. A written spelling that the database would read as a different literal, for example, is refused here.

These two checks run at publication, so a layout constructed with `new` that fails them is refused with `InvariantViolation`, and no operation is returned. In this case the exception reports a layout that does not fit its expression, not necessarily a defect of the library:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

$context = (new Semantics(Dialect::Sqlite))->context([]);
$sum = static fn (): Binary => new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('1'));
$spelled = static fn (string $operator): Layout => new Layout([new Spelled('', '1'), new Spelled(' /* plus */ ', $operator), new Spelled(' ', '1')]);

(new Operation($context, new Select([new ResultColumn($sum())])))->field(0)->name?->value; // => '1 + 1'
(new Operation($context, new Select([new ResultColumn($sum(), null, $spelled('+'))])))->toString(); // => 'SELECT 1 /* plus */ + 1'
new Operation($context, new Select([new ResultColumn($sum(), null, $spelled('-'))])); // throws InvariantViolation
```

A result column constructed without a layout is written in the canonical spelling and named after that text, which is the text the database reads. Layouts are part of the structure, so `new Operation($otherContext, $operation->statement)` keeps them.
