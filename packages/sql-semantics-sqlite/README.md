# SQL Semantics for SQLite

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-sqlite

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE main.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM main.accounts', $context);

echo $ir->toString();
```

The checked complete domain includes projections, joins, predicates, ordering, values, compound queries, selected table changes and transaction control. Table declarations include STRICT, WITHOUT ROWID with a resolved primary key, native INT and ANY types, literal defaults and per-column NOT NULL policies. Table declarations also cover ordered unique keys and explicitly named CHECK constraints over declared columns. Declarations also cover VIRTUAL and STORED generated columns with checked scalar expressions, including forward references to declared columns. The default search order gives temporary tables precedence over main.

For an ordinary ADD COLUMN operation, provide the target table declaration to `context()` and pass that context to `analyze()`. The resulting SQLite `AddColumn` records the resolved schema and table, the prior column order, the table's STRICT mode and the new column. It supports the standard declared types, ordinary untyped columns, literal defaults, builtin collations and NOT NULL conflict policies. SQLite evaluates restrictions that depend on whether the table has rows. For example, adding a NOT NULL column with no default succeeds on an empty SQLite 3.47.2 table and fails on a populated one.

For a generated addition, `analyze()` returns SQLite `AddGeneratedColumn`. The generated expression binds to the resolved prior column ordinals, including prior generated columns. SQLite 3.47.2 supports adding a VIRTUAL column to a populated table and adding a STORED column to an empty table.

Both addition types can carry explicitly named inline CHECK constraints, such as `CONSTRAINT positive CHECK (total > 0)`. A CHECK can read the appended column as well as prior columns. The completed value keeps the constraints in declaration order. SQLite validates existing rows during the addition and uses the first failed constraint's name in diagnostics for later writes. An unnamed check can compare a declared column with a nonnegative decimal integer. The IR stores the checked comparison spelling so SQLite can still derive the diagnostic label when that column is renamed. Within one inline column definition an explicit name applies to later unnamed checks.

Both table declarations and ordinary column additions can carry `ColumnDefaultExpression` with a checked expression. SQLite applies the expression when an insert omits the column. An addition to a populated table also follows SQLite's existing-row fallback rules. Arithmetic such as `DEFAULT (1 + 2)` succeeds when adding to an empty table and fails when adding to a populated table.

A column removal returns `DropColumn` with the resolved absolute table, the prior declared heading and the ordinal to remove. Build the context from the table's CREATE TABLE definition so the analyzer can check keys, surviving CHECK and generated expressions, and the column layout. SQLite evaluates schema-wide effects, such as a separate index or view referring to the affected column, when the IR is executed.

For `CREATE INDEX`, `analyze()` resolves the indexed table through the supplied context and returns `CreateIndex`. The value records the owning schema, ordered `IndexTerm` operands, direction, uniqueness and any partial predicate. It retains native empty membership in a partial predicate, including the numbered parameters that SQLite registers in its discarded operand.

A single INNER, CROSS, LEFT, RIGHT, or FULL join between two declared base tables can use USING or NATURAL. The IR resolves the comparison operands in left-to-right order and keeps both inputs addressable by qualified names. An unqualified merged name selects the right slot for RIGHT and the left slot for INNER, CROSS, and LEFT. FULL combines the left and right slots, keeping the first non-NULL value. SQLite also resolves USING and NATURAL in longer INNER, CROSS, and LEFT chains. Each equality refers to its actual earlier input, and each step retains its own right occurrence. The rendered SELECT uses explicit ON and projection clauses.

INSERT and UPDATE keep their explicit conflict strategy or inherit the target schema's policy. REPLACE is an INSERT with an explicit REPLACE strategy. Execution uses the target's constraint and trigger definitions. INSERT with VALUES or SELECT can record a targetless ON CONFLICT DO NOTHING action.

Concrete types are under `SqlSemantics\Platform\Sqlite\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
