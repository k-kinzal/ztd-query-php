# Statement Models

`SqlSemantics\Facade\Semantics::analyze()` accepts any statement of the selected dialect and grammar version, including DML, DDL, transactions, stored programs, and administrative statements, without a schema. It returns an immutable `SqlSemantics\Statement\Statement` whose `command` is a typed model of the statement.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
$statement = $semantics->analyze('SELECT id, name FROM users WHERE id = ? ORDER BY name LIMIT 10');

$statement->toString(); // 'SELECT id , name FROM users WHERE id = ? ORDER BY name LIMIT 10'
```

A syntax error throws `SqlSemantics\Core\AnalysisException`.

## Models

The models of each database live under `SqlSemantics\Statement\Model\MySql`, `...\PostgreSql`, and `...\Sqlite`, and are generated from the official grammars of all supported versions:

| Kind | Meaning |
|------|---------|
| `Role` interfaces | The SQL positions a value can occupy, such as an expression or a table reference |
| `Value` classes | One SQL form, with named and typed constructor arguments for its variable parts |
| `Choice` enums | A finite set of fixed options, including an absent clause |

Identifiers and literals are fields that keep their spelling and quoting. Model class names end in a signature suffix that tells apart forms with the same grammar name.

## Reconstructing SQL

`Statement::toString()` writes SQL from the model. It keeps identifier spelling, quoting, literals, every SQL choice, and every comment, and puts a single space between tokens except where they must be adjacent. Layout is not kept, and the model does not hold the original SQL or the parser tree.

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

A statement can also be built from models directly, without parsing:

```php
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324;
use SqlSemantics\Statement\Statement;

$commit = new Statement(new CmdWithCommitEndTransOpt_ccca6149('COMMIT', new TransOptWithTransaction_ea573324()));

$commit->toString(); // 'COMMIT TRANSACTION'
```
