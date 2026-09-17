<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class TableSampleTest extends TestCase
{
    public function testTableSampleUsesShadowRows(): void
    {
        $containerInstance = \Testcontainers\Testcontainers::run(PostgreSql16Container::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=test', str_replace('localhost', '127.0.0.1', $containerInstance->getHost()), $containerInstance->getMappedPort(5432)),
            'test',
            'test',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE sample_data (id INTEGER PRIMARY KEY, label TEXT NOT NULL)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            self::assertSame(4, $ztdPdo->exec(
                "INSERT INTO sample_data VALUES (1, 'A'), (2, 'B'), (3, 'C'), (4, 'D')",
            ));

            $bernoulli = $ztdPdo->query(
                'SELECT sampled.id FROM sample_data AS sampled TABLESAMPLE BERNOULLI (100) ORDER BY sampled.id',
            );
            self::assertNotFalse($bernoulli);
            self::assertSame([1, 2, 3, 4], $bernoulli->fetchAll(PDO::FETCH_COLUMN));

            $system = $ztdPdo->query(
                'SELECT id FROM sample_data TABLESAMPLE SYSTEM (100) REPEATABLE (17.5) ORDER BY id',
            );
            self::assertNotFalse($system);
            self::assertSame([1, 2, 3, 4], $system->fetchAll(PDO::FETCH_COLUMN));

            $empty = $ztdPdo->query('SELECT id FROM sample_data TABLESAMPLE BERNOULLI (0)');
            self::assertNotFalse($empty);
            self::assertSame([], $empty->fetchAll(PDO::FETCH_COLUMN));

            $first = $ztdPdo->query(
                'SELECT id FROM sample_data TABLESAMPLE BERNOULLI (50) REPEATABLE (42) ORDER BY id',
            );
            $second = $ztdPdo->query(
                'SELECT id FROM sample_data TABLESAMPLE BERNOULLI (50) REPEATABLE (42) ORDER BY id',
            );
            self::assertNotFalse($first);
            self::assertNotFalse($second);
            self::assertSame($first->fetchAll(PDO::FETCH_COLUMN), $second->fetchAll(PDO::FETCH_COLUMN));

            $physical = $pdo->query('SELECT COUNT(*) FROM sample_data');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
