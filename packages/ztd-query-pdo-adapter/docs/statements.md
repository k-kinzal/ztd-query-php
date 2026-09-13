# Statements and transactions

Prepared statements are rewritten again at execution time so that a statement prepared earlier sees subsequent shadow changes. `bindValue()` retains a value; `bindParam()` retains a reference and observes changes made before the next execution. Driver options and fetch modes are reapplied when the native statement is replaced.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:');
$native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo = ZtdPdo::fromPdo($native);
$pdo->exec("INSERT INTO users VALUES (1, 'Ada'), (2, 'Grace')");
$select = $pdo->prepare('SELECT name FROM users WHERE id = ?');
$id = 1;
$select->bindParam(1, $id, PDO::PARAM_INT);
$select->execute();
assert($select->fetchColumn() === 'Ada');
$id = 2;
$select->execute();
assert($select->fetchColumn() === 'Grace');
$pdo->exec("UPDATE users SET name = 'Grace Hopper' WHERE id = 2");
$select->execute();
assert($select->fetchColumn() === 'Grace Hopper');
```

A normal write has no fetchable result set. `rowCount()` reports affected shadow rows. PostgreSQL and SQLite writes with a supported `RETURNING` clause expose buffered rows through the statement's fetch methods.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:');
$native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo = ZtdPdo::fromPdo($native);
$write = $pdo->prepare('INSERT INTO users (name) VALUES (?) RETURNING id, name');
$write->execute(['Ada']);
assert($write->rowCount() === 1);
assert($write->fetch(PDO::FETCH_ASSOC) === ['id' => 1, 'name' => 'Ada']);
assert($write->fetch() === false);
assert($native->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
```

`beginTransaction()`, `commit()`, and `rollBack()` coordinate native transaction state and shadow state. Committing simulated writes retains them in the shadow; rolling back removes changes made in that transaction.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:');
$native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo = ZtdPdo::fromPdo($native);
$pdo->beginTransaction();
$pdo->exec("INSERT INTO users VALUES (1, 'Ada')");
assert($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1);
$pdo->rollBack();
assert(!$pdo->inTransaction());
assert($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
assert($native->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
```

Fetch behavior that depends on driver capabilities remains driver-specific. For example, SQLite does not support multiple rowsets or arbitrary statement attributes. Check the native driver contract when using these methods.
