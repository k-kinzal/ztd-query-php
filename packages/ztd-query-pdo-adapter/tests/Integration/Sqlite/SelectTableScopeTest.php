<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SelectTableScopeTest extends TestCase
{
    public function testDerivedAggregateReadsShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_employees (id INTEGER PRIMARY KEY, department TEXT, salary INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_employees (id INTEGER PRIMARY KEY, department TEXT, salary INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec("INSERT INTO native_employees VALUES (1, 'Engineering', 120000), (2, 'Engineering', 110000)");
        $ztdPdo->exec("INSERT INTO shadow_employees VALUES (1, 'Engineering', 120000), (2, 'Engineering', 110000)");

        $native = $rawPdo->query('SELECT * FROM (SELECT department, AVG(salary) AS average_salary FROM native_employees GROUP BY department) AS summary WHERE average_salary > 100000');
        $shadow = $ztdPdo->query('SELECT * FROM (SELECT department, AVG(salary) AS average_salary FROM shadow_employees GROUP BY department) AS summary WHERE average_salary > 100000');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testTableValuedFunctionIsNotTreatedAsUnknownTable(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY, tags TEXT)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY, tags TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec('INSERT INTO native_items VALUES (1, \'["php","sql"]\')');
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1, \'["php","sql"]\')');

        $native = $rawPdo->query('SELECT native_items.id, json_each.value FROM native_items JOIN json_each(native_items.tags) ORDER BY json_each.value');
        $shadow = $ztdPdo->query('SELECT shadow_items.id, json_each.value FROM shadow_items JOIN json_each(shadow_items.tags) ORDER BY json_each.value');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testDerivedJoinReadsEveryNestedShadowRelation(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_users (id INTEGER PRIMARY KEY, name TEXT)');
        $rawPdo->exec('CREATE TABLE native_orders (id INTEGER PRIMARY KEY, user_id INTEGER, amount INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_users (id INTEGER PRIMARY KEY, name TEXT)');
        $rawPdo->exec('CREATE TABLE shadow_orders (id INTEGER PRIMARY KEY, user_id INTEGER, amount INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec("INSERT INTO native_users VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec('INSERT INTO native_orders VALUES (1, 1, 30), (2, 1, 20), (3, 2, 10)');
        $ztdPdo->exec("INSERT INTO shadow_users VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec('INSERT INTO shadow_orders VALUES (1, 1, 30), (2, 1, 20), (3, 2, 10)');

        $native = $rawPdo->query('SELECT * FROM (SELECT native_users.name, SUM(native_orders.amount) AS total FROM native_users JOIN native_orders ON native_orders.user_id = native_users.id GROUP BY native_users.name) AS totals ORDER BY name');
        $shadow = $ztdPdo->query('SELECT * FROM (SELECT shadow_users.name, SUM(shadow_orders.amount) AS total FROM shadow_users JOIN shadow_orders ON shadow_orders.user_id = shadow_users.id GROUP BY shadow_users.name) AS totals ORDER BY name');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testNestedUnionBranchesReadShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY, score INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY, score INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec('INSERT INTO native_items VALUES (1, 10), (2, 20), (3, 30)');
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1, 10), (2, 20), (3, 30)');

        $native = $rawPdo->query('SELECT * FROM (SELECT id FROM native_items WHERE score = 10 UNION ALL SELECT id FROM native_items WHERE score = 20 UNION ALL SELECT id FROM native_items WHERE score = 30) AS selected ORDER BY id');
        $shadow = $ztdPdo->query('SELECT * FROM (SELECT id FROM shadow_items WHERE score = 10 UNION ALL SELECT id FROM shadow_items WHERE score = 20 UNION ALL SELECT id FROM shadow_items WHERE score = 30) AS selected ORDER BY id');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testScalarSubqueryAndUserCteReadNestedShadowRelation(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_users (id INTEGER PRIMARY KEY, name TEXT)');
        $rawPdo->exec('CREATE TABLE native_orders (id INTEGER PRIMARY KEY, user_id INTEGER, amount INTEGER)');
        $rawPdo->exec('CREATE TABLE shadow_users (id INTEGER PRIMARY KEY, name TEXT)');
        $rawPdo->exec('CREATE TABLE shadow_orders (id INTEGER PRIMARY KEY, user_id INTEGER, amount INTEGER)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec("INSERT INTO native_users VALUES (1, 'Alice'), (2, 'Bob')");
        $rawPdo->exec('INSERT INTO native_orders VALUES (1, 1, 30), (2, 1, 20), (3, 2, 10)');
        $ztdPdo->exec("INSERT INTO shadow_users VALUES (1, 'Alice'), (2, 'Bob')");
        $ztdPdo->exec('INSERT INTO shadow_orders VALUES (1, 1, 30), (2, 1, 20), (3, 2, 10)');

        $native = $rawPdo->query('WITH totals AS (SELECT user_id, SUM(amount) AS total FROM native_orders GROUP BY user_id) SELECT native_users.name, (SELECT total FROM totals WHERE totals.user_id = native_users.id) AS total FROM native_users ORDER BY native_users.id');
        $shadow = $ztdPdo->query('WITH totals AS (SELECT user_id, SUM(amount) AS total FROM shadow_orders GROUP BY user_id) SELECT shadow_users.name, (SELECT total FROM totals WHERE totals.user_id = shadow_users.id) AS total FROM shadow_users ORDER BY shadow_users.id');

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }

    public function testExistsSubqueryInSelectListReadsShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE native_items (id INTEGER PRIMARY KEY)');
        $rawPdo->exec('CREATE TABLE native_tags (item_id INTEGER, name TEXT)');
        $rawPdo->exec('CREATE TABLE shadow_items (id INTEGER PRIMARY KEY)');
        $rawPdo->exec('CREATE TABLE shadow_tags (item_id INTEGER, name TEXT)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $rawPdo->exec('INSERT INTO native_items VALUES (1), (2)');
        $rawPdo->exec("INSERT INTO native_tags VALUES (1, 'featured')");
        $ztdPdo->exec('INSERT INTO shadow_items VALUES (1), (2)');
        $ztdPdo->exec("INSERT INTO shadow_tags VALUES (1, 'featured')");

        $native = $rawPdo->query("SELECT id, EXISTS(SELECT 1 FROM native_tags WHERE native_tags.item_id = native_items.id AND name = 'featured') AS featured FROM native_items ORDER BY id");
        $shadow = $ztdPdo->query("SELECT id, EXISTS(SELECT 1 FROM shadow_tags WHERE shadow_tags.item_id = shadow_items.id AND name = 'featured') AS featured FROM shadow_items ORDER BY id");

        self::assertNotFalse($native);
        self::assertNotFalse($shadow);
        self::assertSame($native->fetchAll(), $shadow->fetchAll());
    }
}
