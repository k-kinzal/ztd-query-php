# Statement data and SQL reconstruction

`Facade\Semantics::analyze()` structures the complete language selected by its
dialect and grammar release. It accepts statements without requiring declarations
for their tables, columns, or functions. The output is an immutable `Statement`
containing a typed command value. Schema-dependent binding remains a separate API.

`Statement::command` implements `Command`, the role for complete commands and
command sequences. Individual fragments implement `Element` and can be printed
with `Writer::render($fragment)`.

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

## Updating statement structure

Every data-bearing generated field has a corresponding typed `with<Field>()`
method. It returns a new instance of the same concrete form through its
constructor, sharing the unchanged immutable children. `Statement::withCommand()`
replaces the complete command. Neither these methods nor the constructors run
sql-parser, tokenize SQL, or reconstruct and reanalyze SQL text.

For example, this SQLite update builds a WHERE clause from an identifier,
comparison, and integer value:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Model\Sqlite\Value\EcmdWithCmdxSemi_b7577a8f as CommandEnvelope;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprEqNeExpr_49d16f16 as Comparison;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithIdj_e1794d68 as Field;
use SqlSemantics\Statement\Model\Sqlite\Value\OneselectWithSelectDistinctSelcollistFromWhereOptGroupbyOptHavingOptOrderbyOptLimitOpt_218e0475 as Select;
use SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2 as IntegerValue;
use SqlSemantics\Statement\Model\Sqlite\Value\WhereOptWithWhereExpr_93445e09 as Where;

$original = (new Semantics(Dialect::Sqlite))->analyze('SELECT foo FROM items');
$command = $original->command;

if ($command instanceof CommandEnvelope && $command->cmdx instanceof Select) {
    $where = new Where(new Comparison(new Field('foo'), '=', new IntegerValue('1')));
    $select = $command->cmdx->withWhere($where);
    $updated = $original->withCommand($command->withCmdx($select));

    $original->toString(); // SELECT foo FROM items
    $updated->toString();  // SELECT foo FROM items WHERE foo = 1
}
```

The `instanceof` checks select the concrete form returned by the initial analysis;
the update itself accepts only structured values. `withWhere()` takes that
database's WHERE role, never a string. Strings remain only at lexical leaves,
where they preserve identifier quoting, literal spelling, and operator aliases.
Their declared spelling domains are asserted by the constructor. Use an explicit
absence model to remove an optional clause. To change to another SQL form,
construct that form and replace it through its enclosing role.

The shared `Assertion` trait expresses construction invariants using PHP's
standard `assert()`. Generated constructors call named assertions such as
`assertMatchesPattern()` and `assertOperandBindingStrength()`, and state that
children belong to the generated immutable vocabulary. These are programming
contracts, not a validation API or a recoverable error-reporting protocol.
Construction and copying state the same invariants. Assertion execution follows
the application's PHP assertion settings.

Operand placement must preserve the represented grouping. For example, replacing
the left operand of multiplication with an addition requires an explicit
parenthesized-expression value. The generator records upstream expression
precedence and associativity for operand-boundary assertions. The writer never
silently changes the expression or inserts SQL parsed from a string. Parentheses
are part of the structure, including when equal precedence still requires them
on a right operand, as in `10 - (3 - 1)`.

The object graph remains immutable: generated classes are final, their fields
are readonly, and constructors assert that structured children are generated
values. Statement additionally asserts that its root graph contains final
objects with readonly scalar or immutable SQL fields. Mutable custom role
implementations do not satisfy these contracts.

Updates preserve model roles and state lexical and operand-placement invariants.
They do not perform schema binding, database validation, or a complete SQL
semantic analysis. In particular, database types, object existence and execution
success remain outside this structural API. Models still share forms across
grammar releases; updating does not add a release-specific parser check.

### Compatibility

`with<Field>()` methods are additions; generated class names and constructor
argument names are unchanged. Constructors now state their spelling, child, and
operand-placement invariants. `Statement` accepts `Command` instead of arbitrary
`Element`, so callers that previously used it to print fragments should use
`Writer::render()` instead. PostgreSQL and SQLite command sequences remain valid
Statement roots.

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
Its generated `Contract\Contracts` supplies immutable model membership, lexical
spelling domains and operand binding strengths. These are construction facts,
not parser tables; updates do not load parsing or tokenization machinery.
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
