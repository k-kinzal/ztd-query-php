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

A native `BOOLEAN` or `pg_catalog.bool` field is a `BooleanColumnReference` in a projection, including when an independent derived input exports that field into a join. The reference identifies its occurrence and column slot. A read can be used directly in WHERE or combined with logical, NULL and truth tests. DISTINCT retains native Boolean equality, and ORDER BY preserves the selected NULL placement.

A native BIGINT field retains an int8 reference through grouped projections, joined inputs and derived headings. CAST to integer retains PostgreSQL's range check at execution. Casts from Boolean and native text fields retain their respective operand kinds.

The six native signed comparison operators retain both operand widths. Mixed integer and bigint fields or signed literals can be compared in a result column, WHERE or JOIN ON. A comparison with a NULL field produces a nullable native Boolean result.

Ordinary VALUES, INSERT SELECT and UPDATE preserve the native widths of signed operands separately from their destination widths. The database performs signed widening or narrowing and reports out-of-range narrowing during evaluation. Field-only RETURNING exposes bigint with its own native slot, including in a resolved target wildcard.

A simple ORDER BY name can select a projected label. The IR records the chosen output position, direction and effective NULL placement. When that label also names a physical input column, PostgreSQL selects the output alias. A qualified field or expression uses the input scope. The sort key owns that scalar even when the result omits it. For DISTINCT, an exact qualified read of a projected field resolves to its output position. The input key roles include native signed 32-bit and 64-bit fields, Boolean and text fields, signed 32-bit arithmetic, builtin casts, and the explicit builtin byte collation.

A projection can apply the builtin byte collation using `COLLATE pg_catalog."C"` on text literals and resolved native text fields. The value retains its exact collation, including for a typed SQL NULL. A positional ORDER BY on that result sorts by UTF-8 bytes with the stated NULL placement. The same explicit collation on either operand of a builtin text comparison selects byte comparison. The result is Boolean and is NULL if either operand is NULL. The comparison can also control WHERE, ordinary JOIN ON, UPDATE or DELETE.

Ordinary DELETE and UPDATE statements can return named int4 and native text fields from the affected target rows. DELETE returns the deleted row. UPDATE assignments read the old row and RETURNING reads the new row. Text assignments accept decoded text, SQL NULL, a resolved native text field or a checked int4 literal or field. A swap reads both fields from the old row. A complete value records repeated fields or labels by position and preserves the target's `ONLY` policy. All four mutation forms can return native Boolean fields from the target row. UPDATE and DELETE can use these fields in checked Boolean filters.

PostgreSQL INSERT also returns named int4 and native text fields from the inserted row, including text supplied by a database default. When constructing an INSERT directly, build an `Ir\Mutation\Insert\Destination` from the relation and its ordered `Ir\Mutation\Insert\WriteSlot` values. Supply each native kind with its slot. In VALUES, an int4 slot accepts a signed literal or NULL. A text slot also accepts a native text literal. A signed int4 value assigned to text uses the stock PostgreSQL assignment conversion. INSERT SELECT applies that conversion after the SELECT's duplicate policy. The RETURNING list reads slots of the target relation, including fields supplied by its database defaults.

A Boolean destination accepts TRUE, FALSE and NULL in VALUES. A quoted unknown literal such as `' Of '` uses the stock Boolean input rule in VALUES, UPDATE and a nondistinct INSERT SELECT. UPDATE can also read a Boolean field from the old row, so a two-field swap reads both original values. INSERT SELECT accepts a Boolean-valued result. In a DISTINCT projection, use `CAST(NULL AS pg_catalog.bool)` for a typed NULL.

An ordinary CREATE TABLE can use an int4 or int8 literal as a default for SMALLINT, INTEGER, BIGINT or native text. The IR keeps the source type and exact value. PostgreSQL applies the destination conversion when a row uses the default. A narrowing overflow raises SQLSTATE 22003 at that insertion. Supplying an explicit in-range value bypasses the default.

An ordinary BOOLEAN column holds a native TRUE, FALSE, or NULL default, or omits the default. A quoted input such as `' YE '` uses the builtin Boolean input rules during analysis. Use `BOOLEAN` or `pg_catalog.bool` to select the builtin type explicitly. An unqualified `bool` name requires a declaration context that resolves it.

Concrete types are under `SqlSemantics\Platform\PostgreSql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
