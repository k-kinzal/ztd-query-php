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

Declare `COLLATE utf8mb4_bin` on the VARCHAR field or as the table default to read the field by its native position. The declaration can name `CHARACTER SET utf8mb4` explicitly. A field with its own character-set clause needs its own collation. Bare, qualified and wildcard projections of the same field retain the same reference and its declared VARCHAR length. Values retain trailing spaces, and comparisons for ORDER BY and DISTINCT use the collation’s PAD SPACE rules. Use IS NULL and IS NOT NULL on the resolved field in a result, a WHERE filter or a join condition.

Compare a resolved VARCHAR field with another field, a plain text literal or NULL using `=`, `<>`, `<`, `<=`, `>`, `>=` or `<=>`. The field fixes the collation. Ordinary comparisons propagate NULL and `<=>` produces an integer truth value for every operand pair. Use these comparisons as projected results or in SELECT, UPDATE and DELETE filters and local join conditions.

An ordinary single-table UPDATE can assign a resolved VARCHAR field, a plain text literal or NULL to another declared VARCHAR field. The target layout retains the character capacity and nullability. Assignments run in written order, so a later read sees the value stored by an earlier assignment. The native empty SQL mode applies character truncation and substitutes an empty string for NULL in a required field, with warnings.

An ordinary INSERT VALUES can write text, signed integer and NULL literals to an explicit list of resolved native destinations. Each row follows the destination order. With empty SQL mode, NULL in a required VARCHAR causes a single-row INSERT to fail. A multi-row INSERT stores the implicit empty value and emits a warning.

An ordinary `INSERT ... SELECT` can write native VARCHAR fields, text literals, and NULL alongside signed values from a checked SELECT. The field order determines the destination of each expression. `DISTINCT` applies before the target’s storage conversion. Under empty SQL mode this form substitutes the implicit empty string for NULL assigned to a required VARCHAR and warns, including when the SELECT produces one row.

COUNT can read a native VARCHAR field in a global or signed-key grouped query. COUNT skips NULL and COUNT DISTINCT counts the field’s case-sensitive PAD SPACE equivalence classes.

MIN and MAX over a native VARCHAR field use its PAD SPACE ordering. They return the original bytes of an extremal value, including trailing spaces, or NULL when no non-NULL value enters the aggregate. Equal extremal values can have different stored bytes.

An independent derived input in a join can export native VARCHAR fields alongside signed fields. Outer operands read the exact derived occurrence and output position. Native type, collation and character capacity are derived from the checked inner projection.

An ordinary InnoDB `CREATE TABLE` can declare `VARCHAR(n)` with the checked `utf8mb4_bin` collation, including inheritance from an explicit table default. `Schema\CharacterStorage` carries the declared capacity in characters. Each column keeps its own nullability and optional text or NULL default. Excess ASCII whitespace in a default remains in the emitted SQL so the server can report its truncation. Defaults that discard significant characters are rejected during construction.

Use `TINYINT`, `SMALLINT`, `MEDIUMINT`, `INTEGER` or `BIGINT` for an ordinary signed declaration. The model checks the exact signed range of a literal default, including both BIGINT endpoints, and retains its decimal value.

An ordinary signed `PRIMARY KEY` retains the ordered positions of its required fields. Inline and table clauses resolve to the same key type. The column layout records effective defaults for the selected release, including the zero default that MySQL 5.6 can assign when a table key makes a column required. Key terms retain descending storage from MySQL 8.0.

Apply an explicit `COLLATE utf8mb4_bin` to a native VARCHAR field or a plain text literal. The explicit role retains the operand’s bytes and takes precedence over implicit field and literal collations when selecting a supported comparison.

Signed integer `BETWEEN` and `NOT BETWEEN` preserve inclusive bounds, NULL and signed 64-bit range literals. A projected range test has MySQL's native integer truth result.

Concrete types are under `SqlSemantics\Platform\MySql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
