<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SelectQualifiedTableTest extends TestCase
{
    public function testMainQualifiedTableReadsShadowRows(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);
        $ztdPdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

        $statement = $ztdPdo->query('SELECT name FROM main.users WHERE id = 1');

        self::assertNotFalse($statement);
        self::assertSame([['name' => 'Alice']], $statement->fetchAll());
    }
}
