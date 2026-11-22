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

The checked complete domain covers signed integer projections. Configure the grammar release and lexical profile before declaring the catalog. Use a database-qualified relation name for binding or configure a current database in the mode.

Concrete types are under `SqlSemantics\Platform\MySql\Ir`. See the [semantic roles and checked profiles](../sql-semantics/docs/semantics-ir.md), [catalog construction](../sql-semantics/docs/contexts.md), and [native evidence](../sql-semantics/assurance/README.md).
