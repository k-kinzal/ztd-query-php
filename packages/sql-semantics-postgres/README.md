# SQL Semantics for PostgreSQL

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-postgres

```php
use SqlSemantics\Platform\PostgreSql\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE public.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM public.accounts', $context);

echo $ir->toString();
```

The checked complete domain covers signed integer projections, including searched CASE expressions whose result resolves to int4. PostgreSQL integer `BETWEEN` retains each nullable operand, inclusivity, bound order, symmetry and negation. Signed int4 `IS DISTINCT FROM` and `IS NOT DISTINCT FROM` retain the ordered operands and produce a nonnull Boolean. Boolean predicates can record each of the six IS truth tests. Built-in integer operators are emitted with their pg_catalog identity. The profile includes the schema search path.

A resolved ordinary table can supply int4 and native text projection fields. Qualifying a field with its table or alias selects the same occurrence and position. For example, declare `label text` in a complete catalog and project `label, accounts.label` to read the same value twice. An earlier temporary table named `text` shadows that unqualified type. The qualified declaration `label pg_catalog.text` explicitly selects the builtin type. A text field can be tested with `IS NULL` or `IS NOT NULL` as a Boolean result or in a WHERE or ON predicate. An independent derived table in a join can export those native text and integer fields by position.

An external read can resolve to a base table, a view, or a materialized view. The IR records `TableSource::$kind` alongside the schema, name, consumed fields and `ONLY` policy. Include each needed definition in the analysis context in dependency order. Evaluation uses the relation at that identity, so a view reads current data and a materialized view reads its stored rows. The INSERT, UPDATE and DELETE destination constructors require a base table.

Ordinary `WITH` queries keep their ordered SELECT definitions, materialization policies and local reads by definition and output position. A later definition can project the fields of an earlier one. Independent nested `WITH` queries have their own definition sequence. Explicit result labels survive local alias changes. A parameter position shared across definitions must have one native input type.

A native `BOOLEAN` or `pg_catalog.bool` field is a `BooleanColumnReference` in a projection, including when an independent derived input exports that field into a join. The reference identifies its occurrence and column slot. A read can be used directly in WHERE or combined with logical, NULL and truth tests. DISTINCT retains native Boolean equality, and ORDER BY preserves the selected NULL placement.

A native BIGINT field retains an int8 reference through grouped projections, joined inputs and derived headings. CAST to integer retains PostgreSQL's range check at execution. Casts from Boolean and native text fields retain their respective operand kinds.

The six native signed comparison operators retain both operand widths. Mixed integer and bigint fields or signed literals can be compared in a result column, WHERE or JOIN ON. A comparison with a NULL field produces a nullable native Boolean result.

`BETWEEN`, `IS DISTINCT FROM` and their negated forms also accept pure int4 and int8 operands, including explicit `CAST(NULL AS bigint)`. The IR retains each resolved field, signed literal and typed NULL. BETWEEN preserves both inclusive bounds and their symmetry policy. Distinctness returns a native non-null Boolean.

An ordinary persistent `CREATE VIEW` returns `Ir\Schema\View\CreateView` with its resolved defining query, effective heading and native clause choices. A simple base-table projection can declare `LOCAL` or `CASCADED CHECK OPTION`. The optional security flags distinguish omitted settings from explicit true or false. The compiled declaration also accepts a checked standalone `VALUES` definition.

`DROP VIEW` and `DROP MATERIALIZED VIEW` return `Ir\Schema\View\DropView` with the native kind, absolute names in statement order and both clause policies. Provide view declarations through the analysis context for named targets. An optional absent target requires an explicit known schema in a complete context. The rendered SQL leaves dependency checks and transactional effects to PostgreSQL.

`DROP INDEX` returns `Ir\Schema\Index\DropIndex`. Provide the declarations of installed indexes and their indexed relations to `context()`. Include `CREATE SCHEMA` declarations when empty schemas or search order matter.

```php
$context = $semantics->context([
    'CREATE SCHEMA archive',
    'CREATE TABLE archive.accounts (id integer)',
    'CREATE INDEX account_lookup ON archive.accounts (id)',
]);

$drop = $semantics->analyze('DROP INDEX archive.account_lookup', $context);
echo $drop->toString(); // DROP INDEX "archive"."account_lookup" RESTRICT
```

Each installed target records its absolute schema, index name and indexed relation. Guarded absence records whether the selector's schema is missing or the index was absent in the searched namespace. Use `pg_temp` for the current session's temporary schema. Target order, repeats, `IF EXISTS`, dependency mode and `CONCURRENTLY` survive rendering. Indexed relations backed by constraint-owned or partitioned indexes require separate capabilities. PostgreSQL still determines runtime dependency, permission and transaction outcomes under the same external catalog.

A named `CREATE INDEX` on a declared ordinary table returns `Ir\Schema\Index\Btree\CreateIndex` for the stock B-tree profile. The value retains the table's physical integer, bigint and Boolean column kinds, the effective direction and NULL placement of each key, and every `INCLUDE` occurrence. Keys and payload may repeat. A guarded name may already be occupied in the target table's namespace. PostgreSQL determines uniqueness failures and the resulting live catalog state when the SQL runs.

The same B-tree value can carry a direct scalar `IS NULL` or `IS NOT NULL` predicate as a resolved physical slot. For example, `CREATE INDEX queued ON app.orders (id) WHERE app.orders.is_closed IS NULL` checks the exact indexed column if the declared physical type belongs to the supported B-tree profile. A unique partial index checks only the rows that satisfy its predicate.

A persistent heap `CREATE MATERIALIZED VIEW` returns `Ir\Schema\View\CreateMaterializedView` with its effective ordered heading, checked defining query and explicit initial population policy. `REFRESH MATERIALIZED VIEW` returns `Ir\Schema\View\RefreshMaterializedView` for a resolved materialized relation. Include the target's declaration in the context when analyzing a refresh. `WITH NO DATA` makes the snapshot unscannable until a populated refresh. Concurrent refresh and custom storage settings are outside this complete IR domain.

`CREATE TABLE AS` returns `Ir\Schema\Query\CreateTableAs` for a persistent native heap table. It preserves the resolved name and defining query, the ordered catalog heading, optional collision handling and logged or unlogged storage. `WITH DATA` copies the query's rows once. `WITH NO DATA` creates an empty writable table with the same heading. Temporary targets, custom storage and commit actions are outside this complete IR domain.

`ALTER TABLE … RENAME TO` and `ALTER TABLE … RENAME COLUMN … TO` publish distinct `Ir\Schema\Alter` values. The target uses its resolved schema. A column rename retains `ONLY` because the native server uses it to decide the effect on descendants. Supply the table declaration to bind existing names. An optional absent target needs an explicit known schema in a complete context.

`TRUNCATE` publishes `Ir\Schema\Truncate\TruncateTable`. It keeps every resolved table occurrence in order, including duplicates with different `ONLY` choices. The first occurrence determines descent for that table. The value also fixes the owned-sequence policy and foreign-key dependency policy. PostgreSQL applies those rules to the live catalog and can roll the operation back.

A standalone `VALUES` statement returns `Ir\Rows\Values\ValuesQuery`. Each row has the same number of slots and each column has a common native type. Use `columnKind(0)` to read the first type. Signed integers widen to bigint when needed. Untyped NULLs inherit the selected column type, with text as the default for an all-untyped column. The emitted SQL uses PostgreSQL's native column names and retains duplicate rows. A constant table can also supply an independent derived projection or an ordinary WITH member. Local readers refer to its exact column positions. A constant table can also participate in `UNION`, `INTERSECT` or `EXCEPT` as one typed set operand. Its native column types combine with the other operand after its own column types have been determined.

Ordinary VALUES, INSERT SELECT and UPDATE preserve the native widths of signed operands separately from their destination widths. The database performs signed widening or narrowing and reports out-of-range narrowing during evaluation. Field-only RETURNING exposes bigint with its own native slot, including in a resolved target wildcard.

A simple ORDER BY name can select a projected label. The IR records the chosen output position, direction and effective NULL placement. When that label also names a physical input column, PostgreSQL selects the output alias. A qualified field or expression uses the input scope. The sort key owns that scalar even when the result omits it. For DISTINCT, an exact qualified read of a projected field resolves to its output position. The input key roles include native signed 32-bit and 64-bit fields, Boolean and text fields, signed 32-bit arithmetic, builtin casts, and the explicit builtin byte collation.

Grouped results accept ORDER BY over their final output names or positions. The sort keys preserve native aggregate values and NULL placement. LIMIT and OFFSET apply to the grouped result after ordering.

HAVING can compare a grouped int4 field, a signed literal, or a builtin COUNT, MIN, MAX or SUM of the admitted int4 fields. The group predicate can use an aggregate omitted from the result. A Boolean literal or NULL can also control whether the group is included. The group filter runs after WHERE and before result sorting and bounds.

A projection can apply the builtin byte collation using `COLLATE pg_catalog."C"` on text literals and resolved native text fields. The value retains its exact collation, including for a typed SQL NULL. A positional ORDER BY on that result sorts by UTF-8 bytes with the stated NULL placement. The same explicit collation on either operand of a builtin text comparison selects byte comparison. The result is Boolean and is NULL if either operand is NULL. The comparison can also control WHERE, ordinary JOIN ON, UPDATE or DELETE.

Ordinary DELETE and UPDATE statements can return named int4 and native text fields from the affected target rows. DELETE returns the deleted row. UPDATE assignments read the old row and RETURNING reads the new row. Text assignments accept decoded text, SQL NULL, a resolved native text field or a checked int4 literal or field. A swap reads both fields from the old row. A complete value records repeated fields or labels by position and preserves the target's `ONLY` policy. All four mutation forms can return native Boolean fields from the target row. UPDATE and DELETE can use these fields in checked Boolean filters.

PostgreSQL INSERT also returns named int4 and native text fields from the inserted row, including text supplied by a database default. When constructing an INSERT directly, build an `Ir\Mutation\Insert\Destination` from the relation and its ordered `Ir\Mutation\Insert\WriteSlot` values. Supply each native kind with its slot. In VALUES, an int4 slot accepts a signed literal or NULL. A text slot also accepts a native text literal. A signed int4 value assigned to text uses the stock PostgreSQL assignment conversion. INSERT SELECT applies that conversion after the SELECT's duplicate policy. The RETURNING list reads slots of the target relation, including fields supplied by its database defaults.

A Boolean destination accepts TRUE, FALSE and NULL in VALUES. A quoted unknown literal such as `' Of '` uses the stock Boolean input rule in VALUES, UPDATE and a nondistinct INSERT SELECT. UPDATE can also read a Boolean field from the old row, so a two-field swap reads both original values. INSERT SELECT accepts a Boolean-valued result. In a DISTINCT projection, use `CAST(NULL AS pg_catalog.bool)` for a typed NULL.

An ordinary CREATE TABLE can use an int4 or int8 literal as a default for SMALLINT, INTEGER, BIGINT or native text. The IR keeps the source type and exact value. PostgreSQL applies the destination conversion when a row uses the default. A narrowing overflow raises SQLSTATE 22003 at that insertion. Supplying an explicit in-range value bypasses the default.

An ordinary BOOLEAN column holds a native TRUE, FALSE, or NULL default, or omits the default. A quoted input such as `' YE '` uses the builtin Boolean input rules during analysis. Use `BOOLEAN` or `pg_catalog.bool` to select the builtin type explicitly. An unqualified `bool` name requires a declaration context that resolves it.

Concrete types are under `SqlSemantics\Platform\PostgreSql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
