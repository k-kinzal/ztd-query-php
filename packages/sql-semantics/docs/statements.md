# Statement data and SQL reconstruction

`Facade\Semantics::analyze()` structures the complete language selected by its
dialect and grammar release. It accepts statements without requiring declarations
for their tables, columns, or functions. The output is an immutable `Statement`
containing a typed command value. Schema-dependent binding remains a separate API.

The models under `Statement\Model\{MySql,PostgreSql,Sqlite}` contain:

- `Role` interfaces describing which SQL positions a value can occupy.
- `Value` classes with named, typed arguments for a particular SQL form.
- `Choice` enums for finite sets of fixed SQL options, including absent clauses.

Every concrete value writes its own fixed syntax and delegates the variable
parts to its fields. A simple example constructs a transaction command entirely
from data, without calling an analyzer or parser:

```php
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324;
use SqlSemantics\Statement\Statement;

$transaction = new TransOptWithTransaction_ea573324();
$commit = new Statement(new CmdWithCommitEndTransOpt_ccca6149('COMMIT', $transaction));
$end = new Statement(new CmdWithCommitEndTransOpt_ccca6149('END', $transaction));

$commit->toString(); // COMMIT TRANSACTION
$end->toString();    // END TRANSACTION
```

Identifiers and literals retain their lexical spelling, so quoting and values
survive reconstruction. A keyword accepted as a name is an identifier field,
not a fixed keyword. `Writer` separates values while preserving distinctions
such as a MySQL built-in function's adjacent opening parenthesis versus an
identifier followed by a parenthesis. Layout and source comments are discarded.

There are no generic child arrays, production ordinals, source positions, parser
nodes, original SQL, or opaque statement fragments in the model. The grammar
labels appear only in generated type names. Forwarding productions disappear;
their child implements the enclosing roles. Fixed productions become enum
choices where possible. SQL terms such as `IS UNKNOWN` and `EXCLUDE NO OTHERS`
are ordinary language constructs, never unclassified results.

## Generating the complete vocabulary

```sh
cd packages/sql-semantics-mysql
composer build:models
composer build:models:check
```

Run these commands from the selected database package, or from its split repository.
Its Composer scripts call `vendor/bin/build-models.php` from the shared runtime
with `--dialect` and `--output=resources`. The compiler reads the official
Bison/Lemon grammars for every registered release of that database: MySQL 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1,
9.1.0, PostgreSQL 17.2, and SQLite 3.47.2. Grammar source URLs are selected in
`bin/build-models.php`; downloaded sources are cached under `build/sources`.
The grammar readers are development dependencies only.

Each visible alternative gets either a concrete constructor, a finite choice,
or a forwarding recipe. Parser-internal actions have no model data. Concrete
names include a deterministic signature suffix to distinguish forms across
releases. Shared forms implement the union of the roles they can occupy across
releases. Generation always processes the selected database's whole release set together.

Each database package's `resources/models` contains its parser-independent definitions.
`resources/mapping` contains version-specific construction recipes, loaded only
by the database `Platform::values()` through `Core/Analysis/ValueReader::fromFile()`. During analysis the reader visits the transient
parser tree, constructs typed values, and releases that tree. Statements never
retain or consult the recipes. Missing recipes are resource errors; there is no
fallback class or raw-SQL escape hatch.

Generation stages its output under the calling package's `build/model-resources/<dialect>`, then installs changed
files and removes obsolete generated PHP files. `--check` compares all filenames
and contents, including additions and removals, without changing the committed
resources. CI runs this check, strict autoload validation, PHPStan over the models,
and Deptrac over the full `Statement` namespace. That layer may depend only on
itself, so a parser dependency in any statement or generated model fails lint.

The [round-trip fuzz property](packages.md#fuzzing) checks reconstruction using all
three sql-faker seed corpora and mutated generation plans. This complements the
compiler's total grammar coverage with a check of actual SQL output, including
lexical boundaries that are significant to a parser.
