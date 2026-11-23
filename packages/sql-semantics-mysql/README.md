# SQL Semantics for MySQL

Requires PHP 8.1 or later. See [grammar releases and usage](../sql-semantics/) for the common interface.

    composer require k-kinzal/sql-semantics-mysql

```php
use SqlSemantics\Platform\MySql\Semantics;

$semantics = new Semantics();
$context = $semantics->context(['CREATE TABLE app.accounts (id INTEGER)']);
$ir = $semantics->analyze('SELECT id, accounts.id FROM app.accounts', $context);

echo $ir->toString();
```

The checked complete domain includes signed integers and native VARCHAR projections. Configure the grammar release and lexical profile before declaring the catalog. Use a database-qualified relation name for binding or configure a current database in the mode.

Declare a VARCHAR field with `COLLATE utf8mb4_bin` to read it by its native position. The declaration can name `CHARACTER SET utf8mb4` explicitly. Bare, qualified and wildcard projections of the same field retain the same reference and its declared VARCHAR length. Values retain trailing spaces, and comparisons for ORDER BY and DISTINCT use the collation’s PAD SPACE rules. Use IS NULL and IS NOT NULL on the resolved field in a result, a WHERE filter or a join condition.

Compare a resolved VARCHAR field with another field, a plain text literal or NULL using `=`, `<>`, `<`, `<=`, `>`, `>=` or `<=>`. The field fixes the collation. Ordinary comparisons propagate NULL and `<=>` produces an integer truth value for every operand pair. Use these comparisons as projected results or in SELECT, UPDATE and DELETE filters and local join conditions.

An ordinary single-table UPDATE can assign a resolved VARCHAR field, a plain text literal or NULL to another declared VARCHAR field. The target layout retains the character capacity and nullability. Assignments run in written order, so a later read sees the value stored by an earlier assignment. The native empty SQL mode applies character truncation and substitutes an empty string for NULL in a required field, with warnings.

An ordinary INSERT VALUES can write text, signed integer and NULL literals to an explicit list of resolved native destinations. Each row follows the destination order. With empty SQL mode, NULL in a required VARCHAR causes a single-row INSERT to fail. A multi-row INSERT stores the implicit empty value and emits a warning.

Signed integer `BETWEEN` and `NOT BETWEEN` preserve inclusive bounds, NULL and signed 64-bit range literals. A projected range test has MySQL's native integer truth result.

Concrete types are under `SqlSemantics\Platform\MySql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
