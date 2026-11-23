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

A projection can apply the builtin byte collation using `COLLATE pg_catalog."C"` on text literals and resolved native text fields. The value retains its exact collation, including for a typed SQL NULL. A positional ORDER BY on that result sorts by UTF-8 bytes with the stated NULL placement.

Ordinary DELETE and UPDATE statements can return named int4 and native text fields from the affected target rows. DELETE returns the deleted row. UPDATE assignments read the old row and RETURNING reads the new row. Text assignments accept decoded text, SQL NULL, a resolved native text field or a checked int4 literal or field. A swap reads both fields from the old row. A complete value records repeated fields or labels by position and preserves the target's `ONLY` policy.

PostgreSQL INSERT also returns named int4 and native text fields from the inserted row, including text supplied by a database default. When constructing an INSERT directly, build an `Ir\Mutation\Insert\Destination` from the relation and its ordered `Ir\Mutation\Insert\WriteSlot` values. Supply each native kind with its slot. In VALUES, an int4 slot accepts a signed literal or NULL. A text slot also accepts a native text literal. A signed int4 value assigned to text uses the stock PostgreSQL assignment conversion. INSERT SELECT applies that conversion after the SELECT's duplicate policy. The RETURNING list reads slots of the target relation, including fields supplied by its database defaults.

Concrete types are under `SqlSemantics\Platform\PostgreSql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
