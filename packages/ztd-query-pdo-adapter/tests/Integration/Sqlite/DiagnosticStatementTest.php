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
final class DiagnosticStatementTest extends TestCase
{
    public function testExplainQueryPlanExecutesAgainstPhysicalDatabase(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $statement = $ztdPdo->query('EXPLAIN QUERY PLAN SELECT * FROM users WHERE id = 1');

        self::assertNotFalse($statement);
        self::assertNotSame([], $statement->fetchAll());
    }
}
