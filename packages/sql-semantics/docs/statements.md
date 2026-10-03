# Statement Models

`SqlSemantics\Facade\Semantics::analyze()` accepts any statement of the selected dialect and grammar version, including DML, DDL, transactions, stored programs, and administrative statements, without a schema. It returns an immutable `SqlSemantics\Statement\Statement` whose `command` is a typed model of the statement.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
$statement = $semantics->analyze('SELECT id, name FROM users WHERE id = ? ORDER BY name LIMIT 10');

$statement->toString(); // 'SELECT id , name FROM users WHERE id = ? ORDER BY name LIMIT 10'
```

A syntax error throws `SqlSemantics\Core\AnalysisException`. A statement analyzed with the statements it depends on also resolves its table names; see [dependencies](dependencies.md).

## The language

`Semantics` reads SQL in one language: a dialect, a grammar release, a mode, and a parameter syntax. `Semantics::language()` answers it as a `SqlSemantics\Core\Language`.

A mode is the session settings of a database that change how it reads text. MySQL's `sql_mode` is one: under `ANSI_QUOTES` the server reads `"x"` as an identifier, under `NO_BACKSLASH_ESCAPES` a backslash is an ordinary character, `PIPES_AS_CONCAT` makes `||` concatenation, and `HIGH_NOT_PRECEDENCE` changes what `NOT a = b` means. Pass the value the session reports as `SqlSemantics\Platform\MySql\Mode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES')`; the statement is then tokenized, parsed, and written as that session would read it. PostgreSQL and SQLite have no mode.

A parameter syntax says which markers are bound parameters. The server's own are `?` for MySQL, `$1` for PostgreSQL, and `?`, `?1`, `:name`, `@name`, and `$name` for SQLite. The named placeholder `:name` is the other common way an application binds a parameter, and MySQL and PostgreSQL reject it; `SqlSemantics\Core\Parameters::Named` reads it as a parameter in every dialect, as a dialect extension, and keeps it in the model.

## Statement boundaries

A server reads one statement at a time and stops at the semicolon that ends it, while a semicolon inside a string, a comment, a compound statement, a trigger body, or a rule action ends nothing. `Semantics::split()` finds the boundaries the same way and answers the text of each statement, ending with its own terminator; trailing whitespace and comments stay with the last statement. `Semantics::analyzeAll()` analyzes each of them.

```php
$semantics->split("SELECT 1; CREATE PROCEDURE p() BEGIN SELECT ';'; END; SELECT 2");
// ['SELECT 1;', " CREATE PROCEDURE p() BEGIN SELECT ';'; END;", ' SELECT 2']
```

The client-side `DELIMITER` command of the mysql client is not SQL and is not read.

## Models

The models of each database live under `SqlSemantics\Statement\Model\MySql`, `...\PostgreSql`, and `...\Sqlite`, and are generated from the official grammars of all supported versions:

| Kind | Meaning |
|------|---------|
| `Role` interfaces | The SQL positions a value can occupy, such as an expression or a table reference |
| `Value` classes | One SQL form, with named and typed constructor arguments for its variable parts |
| `Choice` enums | A finite set of fixed options, including an absent clause |

Identifiers and literals are fields that keep their spelling and quoting. Model class names end in a signature suffix that tells apart forms with the same grammar name.

## Traversal

Every value lists the values it is made of with `children()`, in writing order, and rebuilds itself around replacements with `map()`, so a statement of any dialect and release can be searched and rewritten without naming its classes. `SqlSemantics\Statement\Traversal` builds on that:

| Method | Answers |
|--------|---------|
| `walk($root)` | Every value, each before its own children |
| `find($root, $class)` | The values of a class or role interface, in writing order |
| `rewrite($root, $replace)` | The value rebuilt from the leaves up, giving every value, children first, to the function |

```php
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\MySql\Role\TableIdentForm;
use SqlSemantics\Statement\Model\MySql\Value\TableIdentWithIdentIdent_040003e0 as QualifiedTable;
use SqlSemantics\Statement\Traversal;

$tables = Traversal::find($statement->command, TableIdentForm::class);
$unqualified = Traversal::rewrite($statement->command, static fn (Element $value): Element => $value instanceof QualifiedTable ? $value->ident2 : $value);
```

A value whose children are all kept is kept itself, so `rewrite()` gives the function the values of the statement unchanged until something below them is replaced, and a value found earlier, such as the name of a resolved reference, can be recognized by identity:

```php
$reference = $query->resolution->tables()[0];
$shadowed = Traversal::rewrite($query->command, static fn (Element $value): Element => $value === $reference->value ? $builder->table('shadow_users') : $value);
```

A replacement must be a value the position accepts: `map()` checks it against the role of the position and throws `InvalidArgumentException` otherwise. Lexical fields such as a name or a literal spelling, and comments, are not child values; the typed `with*()` methods change them. A rewritten command becomes a statement again with `withCommand()`, which requires it to be [valid SQL](#validity) of the language.

## Reconstructing SQL

`Statement::toString()` writes SQL from the model. It keeps identifier spelling, quoting, literals, every SQL choice, and every comment, and puts a single space between tokens except where they must be adjacent. Layout is not kept, and the model does not hold the original SQL or the parser tree.

Nothing else is lost. The SQL a statement writes has the syntax of the SQL it was analyzed from: the same rules and alternatives of the grammar, the same tokens spelled the same way, and the same comments before the same tokens. Only whitespace and the letter case of fixed words, such as keywords, may differ. `SqlSemantics\Core\Verification\Losslessness` states this: `difference($sql, $written)` answers the first difference between the syntax of two texts, or null. The round-trip fuzzing of each database package requires it for every statement sql-faker generates.

```php
use SqlSemantics\Core\Verification\Losslessness;

$sql = "select  id AS name from users -- audited\n";
(new Losslessness($semantics->language()))->difference($sql, $semantics->analyze($sql)->toString()); // null
```

## Validity

A statement is always valid SQL of its language. `Statement::$syntax` is the language it is written in, the `SqlSemantics\Core\Language` of the `Semantics` that analyzed it, and building a statement, by analysis, by `new Statement($language, $command)`, or by `withCommand()` and `withComments()`, requires the SQL it writes to be read back by that language as the same command with the same comments. A statement that would be written as other SQL is not built; `SqlSemantics\Statement\StatementException` says why:

| Rewrite | Why it is refused |
|---------|-------------------|
| A name replaced with a reserved word, such as `select` | The release does not parse the SQL |
| `b` in `a * b` replaced with `c + d` | The SQL `a * c + d` is read with another grouping; replace it with a parenthesized expression instead |
| A comment `# note` after a PostgreSQL statement | PostgreSQL reads `#` as an operator |
| A MySQL 8.4 common table expression in a statement of MySQL 5.6 | The release has no such form |

```php
use SqlSemantics\Statement\StatementException;

try {
    $statement->withCommand($rewritten);
} catch (StatementException $refused) {
    // the rewrite would change what the server reads
}
```

A value itself is checked when it is built: its lexical fields must be spellings of their tokens, and a replacement in `map()` must occupy the role of its position; either failure is an `InvalidArgumentException`, a defect of the code that built the value. Whether values combine into SQL the release reads as intended is a property of the whole statement, which only the statement can check. Reading back costs one parse of the statement; a statement built again around the same command and comments, as analysis does with dependencies, is not read again.

## Comments

Comments change what a server does: MySQL reads optimizer hints such as `/*+ SET_VAR(...) */` and executable comments such as `/*!80000 ... */`, PostgreSQL extensions read hints from a leading comment, and monitoring tools read tags such as `/* traceparent='...' */`. The model keeps every comment where it was written.

A comment belongs to the outermost value whose symbol begins with the token the comment precedes, and is held by that value's `comments` field as a `SqlSemantics\Statement\Comments`. A position counts the symbols of the value's SQL form from zero, fixed words and fields alike, so the comment stays with its symbol when other fields are replaced. Comments before the first token and after the last one belong to the `Statement` itself, at `Statement::BEFORE` and `Statement::AFTER`, so they survive `withCommand()`.

```php
$statement = $semantics->analyze('SELECT /*+ MAX_EXECUTION_TIME(1000) */ id FROM users -- audited');

$statement->comments->before(Statement::AFTER); // ['-- audited']
$statement->toString(); // "SELECT /*+ MAX_EXECUTION_TIME(1000) */ id FROM users -- audited"
```

Every comment keeps its delimiters, and a line comment ends before its line break; the writer ends its line before the next token. When the MySQL lexer reads the body of an executable comment as SQL, its opening delimiter, such as `/*!80000`, and its closing `*/` are kept as separate comments around the values they enclose.

Every value class accepts a `Comments` as its last constructor argument and offers `withComments()`:

```php
use SqlSemantics\Statement\Comments;

$select = $statement->command->withComments(new Comments([1 => ['-- reviewed']]));
```

A statement can also be built from models directly, in the language it is written in:

```php
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324;
use SqlSemantics\Statement\Statement;

$sqlite = new Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
$commit = new Statement($sqlite->language(), new CmdWithCommitEndTransOpt_ccca6149('COMMIT', new TransOptWithTransaction_ea573324()));

$commit->toString(); // 'COMMIT TRANSACTION'
```
