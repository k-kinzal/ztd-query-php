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

`Statement::toString()` writes SQL from the model. It keeps identifier spelling, quoting, literals, and every SQL choice, and puts a single space between tokens except where they must be adjacent. Layout and comments are not kept, and the model does not hold the original SQL or the parser tree.

A statement can also be built from models directly, without parsing:

```php
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324;
use SqlSemantics\Statement\Statement;

$commit = new Statement(new CmdWithCommitEndTransOpt_ccca6149('COMMIT', new TransOptWithTransaction_ea573324()));

$commit->toString(); // 'COMMIT TRANSACTION'
```
