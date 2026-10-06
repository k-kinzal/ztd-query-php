# SQL Semantics for PostgreSQL

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics-postgres.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics-postgres)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--postgres-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-postgres/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for PostgreSQL adds PostgreSQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md): the statement structure classes of the official PostgreSQL grammars, the PostgreSQL rules for resolving names and deriving types, NULL facts and diagnostics, and the PostgreSQL spelling of rendered SQL. Installing it also installs the shared runtime, and `Dialect::PostgreSql` selects PostgreSQL in the runtime's `Semantics`. No database connection is needed.

## Requirements

- PHP 8.1+
- No PHP extension

## Support Syntax

The following grammar releases are supported. Pass the version tag as the second argument of `Semantics`; omitting it selects the default. A statement is read with the grammar of the selected release only.

| Version | Version tag | Default |
|---------|-------------|---------|
| 16.6 | `pg-16.6` | |
| 17.2 | `pg-17.2` | Yes |

## Installation

```bash
composer require k-kinzal/sql-semantics-postgres
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::PostgreSql);
$users = $semantics->analyze('CREATE TABLE users (id integer NOT NULL PRIMARY KEY, name text)', []);
$query = $semantics->analyze('select ID, upper(name) as label from Users where id > $1', [$users]);

$query->toString(); // => 'SELECT id, upper(name) AS label FROM users WHERE id > $1'
$query->field('id')->type->descriptor->name(); // => 'integer'
$query->field('id')->nullability; // => Nullability::NotNull
$query->field('label')->type->descriptor->name(); // => 'text'
$semantics->analyze('SELECT "ID" FROM users', [$users])->facts->diagnostics[0]->message(); // => 'Column ID does not exist.'
```

The CREATE TABLE above is analyzed in a complete, empty context (`[]`) so that `integer` and `text` denote the built-in types; see [Type names in open contexts](#type-names-in-open-contexts).

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md) for operations, facts, contexts, rendering and guarantees.

### Releases

`pg-16.6` and `pg-17.2` are read with their own grammars. Pass the full version tag; PostgreSQL has no session mode that changes how text is read, so passing a `Mode` is refused.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

(new Semantics(Dialect::PostgreSql, 'pg-16.6'))->profile()->grammar->value; // => 'pg-16.6'
```

### Names

An unquoted identifier is folded to lower case when it is read; a quoted identifier keeps its case. After that, names are compared exactly: relation, schema, column and output field names alike. Rendered SQL quotes a name with double quotes when the bare spelling would read differently, for example a name with upper-case letters or a reserved keyword.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Facade\SqlNames;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\AbsentField;

$semantics = new Semantics(Dialect::PostgreSql);

$semantics->analyze('SELECT "Name", Name FROM t')->toString(); // => 'SELECT "Name", name FROM t'
$semantics->analyze('SELECT 1 AS Total')->lookupField('Total') instanceof AbsentField; // => true
SqlNames::table(new QualifiedName(new Name('Users'), new Name('app')), $semantics->profile()); // => 'app."Users"'
SqlNames::table(new QualifiedName(new Name('user')), $semantics->profile()); // => '"user"'
```

Unaliased output columns are named by PostgreSQL's own rules, for example after a function (`now`), a cast target type, or `?column?`; these names do not depend on how the expression is spelled.

### Search path

An unqualified relation name is searched in `pg_temp`, then `pg_catalog`, then the schemas of the search path; `pg_temp` and `pg_catalog` are searched where the path lists them when it does. The default path is `public`. An unqualified declaration belongs to the first schema of the path other than `pg_temp` and `pg_catalog`.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

$default = new Semantics(Dialect::PostgreSql);
$app = new Semantics(Dialect::PostgreSql, null, null, ParameterStyle::Native, new SearchPath('app', 'public'));

array_map(static fn ($schema) => $schema->value, $default->context()->searchPath); // => ['pg_temp', 'pg_catalog', 'public']
array_map(static fn ($schema) => $schema->value, $app->context()->searchPath); // => ['pg_temp', 'pg_catalog', 'app', 'public']
$app->context()->declarationSchema->value; // => 'app'

$users = $app->analyze('CREATE TABLE users (id integer)', []);
$query = $app->analyze('SELECT id FROM app.users', [$users]);
$query->facts->relation($query->inputRelation())->table instanceof DeclaredTable; // => true
```

Because `pg_temp` is searched first, an unqualified name in a partial context (`$semantics->context([...], false)`) resolves conditionally even when it is declared: an undeclared temporary table could hide it. Qualify the name, or use a complete context.

### Parameters

The native parameter style reads `$1`, `$2`, ... markers. `ParameterStyle::Named` adds `:name` markers, as PDO accepts them; without it, `:name` is a syntax error. A parameter has the type a cast or the surrounding expression gives it, and otherwise depends on the value bound to it.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$semantics = new Semantics(Dialect::PostgreSql);
$query = $semantics->analyze('SELECT $1::int, $2');

$query->field(0)->type->descriptor->name(); // => 'integer'
$query->field(1)->type->missing[0]->describe(); // => 'the value bound to parameter $2'
(new Semantics(Dialect::PostgreSql, null, null, ParameterStyle::Named))->analyze('SELECT :id')->toString(); // => 'SELECT :id'
$semantics->analyze('SELECT :id'); // throws AnalysisException
```

### Strings

The profile reads SQL with `standard_conforming_strings = on` and a UTF-8 server encoding. Escape strings, dollar-quoted strings and Unicode escapes are decoded to their exact text, and rendered as standard strings.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

(new Semantics(Dialect::PostgreSql))->analyze("SELECT \$\$it's\$\$, U&'d\\0061t', 'a\\b'")->toString(); // => "SELECT 'it''s', 'dat', 'a\\b'"
```

### Type names in open contexts

In an open or partial context, a type name written as an identifier, such as `text`, `int4` or `date`, is `Dependent`: a relation of `pg_temp` that the context does not know could define a row type of that name, and it would be found before `pg_catalog`. Type names that the grammar reads as keywords, such as `integer`, `bigint` or `varchar`, always denote the built-in types. Analyze against a complete context, `[]` when no declarations are needed, to have the built-in types for every name:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

$semantics = new Semantics(Dialect::PostgreSql);

$semantics->analyze("SELECT 'a'::text")->field(0)->type instanceof Dependent; // => true
$semantics->analyze("SELECT 'a'::text", [])->field(0)->type instanceof Known; // => true
$semantics->analyze('CREATE TABLE t (name text)')->declarations()[0]->columns[0]->type instanceof NamedOnPath; // => true
```

A user-defined type is always `Dependent` on its definition, because version 1 contexts cannot declare types.

### Relation kinds

Every declaration states the kind of relation it declares: CREATE TABLE (with AS, PARTITION OF and SELECT INTO) declares a base table, CREATE VIEW and CREATE RECURSIVE VIEW a view, CREATE MATERIALIZED VIEW a materialized view, CREATE FOREIGN TABLE a foreign table, and CREATE SEQUENCE a sequence. A statement that names a declared relation of a kind it does not accept reports the server's error: DROP, COMMENT or ALTER written with another kind, ALTER TABLE actions the kind does not support, REFRESH MATERIALIZED VIEW of anything but a materialized view, INSERT, UPDATE, DELETE and MERGE into a sequence or a materialized view, TRUNCATE, LOCK, COPY, CLUSTER, REINDEX, CREATE INDEX, CREATE STATISTICS, CREATE TRIGGER, CREATE RULE and CREATE POLICY on unsupported kinds, TABLESAMPLE of a view, a foreign table or a sequence, and row locks on a sequence or a materialized view.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Statement\Declaration\RelationKind;

$semantics = new Semantics(Dialect::PostgreSql);
$view = $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a', []);
$sequence = $semantics->analyze('CREATE SEQUENCE s', []);

$view->declarations()[0]->kind; // => RelationKind::View
$semantics->analyze('DROP TABLE v', [$view])->facts->diagnostics[0]->message(); // => '"v" is not a table'
$semantics->analyze('SELECT * FROM s FOR UPDATE', [$sequence])->facts->diagnostics[0]->message(); // => 'cannot lock rows in sequence "s"'
```

### Generated columns

A column with `GENERATED ALWAYS AS (expression) STORED` is declared as generated, and so is a column inherited from a generated column of a parent or of the partitioned table, or copied by `LIKE ... INCLUDING GENERATED`. An identity column is not generated in this sense. The analysis reports the server's errors for a value other than DEFAULT written into a generated column by INSERT, UPDATE, INSERT ... ON CONFLICT DO UPDATE and MERGE, for a generated column named by COPY, for a BEFORE trigger's WHEN condition that reads a generated column of NEW, and for definitions that misuse a generated column: a generation expression or a partition key that uses one, a foreign key on one with an ON UPDATE or ON DELETE action that would change it, an inherited column whose generation does not fit its parents, and ALTER TABLE actions such as SET DEFAULT on a generated column or DROP EXPRESSION on a regular one.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$semantics = new Semantics(Dialect::PostgreSql);
$table = $semantics->analyze('CREATE TABLE t (a int, b int GENERATED ALWAYS AS (a * 2) STORED)', []);

$table->declarations()[0]->columns[1]->generated; // => true
$semantics->analyze('INSERT INTO t VALUES (1, 2)', [$table])->facts->diagnostics[0]->message(); // => 'cannot insert a non-DEFAULT value into column "b"'
$semantics->analyze('UPDATE t SET a = 1, b = DEFAULT', [$table])->facts->diagnostics; // => []
```

## Limitations

- Type names depend on the declarations of earlier searched schemas in open and partial contexts, as described above.
- The profile fixes `standard_conforming_strings = on` and a UTF-8 server encoding; SQL written for other settings is read as if these settings were in effect.
- Version 1 contexts declare relations only. Functions, operators, types and other catalog objects that are not built in are missing inputs.
- Some checks of generation expressions are not reported: a system column or the whole row used in a generation expression, and, because a declaration does not keep generation expressions, conflicting generation expressions of inherited columns and changing the type of a column a generation expression uses. A column of a view is never generated, so writing a generated base column through an updatable view is not reported.
- A declaration does not tell a partitioned table from a plain one, so checks that depend on partitioning (ATTACH PARTITION, PARTITION OF, partitioned-table restrictions) are not reported. Whether a view or a foreign table accepts INSERT, UPDATE, DELETE, COPY FROM or TRUNCATE depends on its definition, its triggers or its foreign-data wrapper, and is not reported either.
- Sequences that serial and identity columns create are not declarations, so commands on sequences are checked only against sequences created with CREATE SEQUENCE. Row locks that FOR UPDATE pushes into a subquery or a view are not checked.
- The server stops at the first error; the analysis reports every diagnostic it finds, so a statement can carry problems the server would never reach.
- Version tags must be written in full (`pg-16.6`).

See [Guarantees](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/docs/guarantees.md) for the limits that apply to every database.

## License

MIT License. See [LICENSE](LICENSE) for details.
