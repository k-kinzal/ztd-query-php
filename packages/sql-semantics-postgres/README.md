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

The checked complete domain covers signed integer projections, including searched CASE expressions whose result resolves to int4. PostgreSQL integer `BETWEEN` retains each nullable operand, inclusivity, bound order, symmetry and negation. Built-in integer operators are emitted with their pg_catalog identity. The profile includes the schema search path.

Ordinary DELETE and UPDATE statements can return named int4 fields from the affected target rows. DELETE returns the deleted row. UPDATE assignments read the old row and RETURNING reads the new row. A complete value records repeated fields or labels by position and preserves the target's `ONLY` policy.

PostgreSQL INSERT also returns named int4 fields from the inserted row. When constructing an INSERT directly, build an `Ir\Mutation\Insert\Destination` from the relation's slot layout and the ordered write references. The RETURNING list reads slots of that same relation, including a field supplied by its database default.

Concrete types are under `SqlSemantics\Platform\PostgreSql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
