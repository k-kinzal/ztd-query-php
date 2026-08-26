<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\PostgreSqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class MergeTest extends TestCase
{
    public function testMergeSimulatesEveryActionSourceShapeAndPreparedParameter(): void
    {
        [$schemaName, $pdo] = PostgreSqlContainer::createTestSchema();

        try {
            $pdo->exec('CREATE TABLE merge_target (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $pdo->exec('CREATE TABLE merge_source (id INTEGER PRIMARY KEY, name TEXT NOT NULL, remove_row BOOLEAN NOT NULL)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            self::assertSame(2, $ztdPdo->exec(
                "INSERT INTO merge_target VALUES (1, 'old'), (3, 'deleted')",
            ));
            self::assertSame(3, $ztdPdo->exec(
                "INSERT INTO merge_source VALUES (1, 'updated', FALSE), (2, 'inserted', FALSE), (3, 'unused', TRUE)",
            ));

            self::assertSame(3, $ztdPdo->exec(
                'MERGE INTO merge_target AS target USING merge_source AS source '
                . 'ON target.id = source.id '
                . 'WHEN MATCHED AND source.remove_row THEN DELETE '
                . 'WHEN MATCHED THEN UPDATE SET name = source.name '
                . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (source.id, source.name)',
            ));
            $afterMixedActions = $ztdPdo->query('SELECT id, name FROM merge_target ORDER BY id');
            self::assertNotFalse($afterMixedActions);
            self::assertSame([
                ['id' => 1, 'name' => 'updated'],
                ['id' => 2, 'name' => 'inserted'],
            ], $afterMixedActions->fetchAll());

            self::assertSame(0, $ztdPdo->exec(
                'MERGE INTO merge_target AS target USING (VALUES (2, TRUE)) AS source(id, skip_row) '
                . 'ON target.id = source.id '
                . 'WHEN MATCHED AND source.skip_row THEN DO NOTHING '
                . 'WHEN MATCHED THEN DELETE',
            ));

            $prepared = $ztdPdo->prepare(
                'MERGE INTO merge_target AS target '
                . 'USING (VALUES ($1::INTEGER, $2::TEXT, $3::BOOLEAN)) AS source(id, name, remove_row) '
                . 'ON target.id = source.id '
                . 'WHEN MATCHED AND source.remove_row THEN DELETE '
                . 'WHEN MATCHED THEN UPDATE SET name = source.name '
                . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (source.id, source.name)',
            );
            self::assertInstanceOf(PDOStatement::class, $prepared);
            self::assertTrue($prepared->execute([1, 'ignored', true]));
            self::assertSame(1, $prepared->rowCount());
            self::assertTrue($prepared->execute([4, 'prepared', false]));
            self::assertSame(1, $prepared->rowCount());

            self::assertSame(1, $ztdPdo->exec(
                "WITH incoming(id, name) AS (VALUES (5, 'cte')) "
                . 'MERGE INTO merge_target AS target USING incoming AS source '
                . 'ON target.id = source.id '
                . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (source.id, source.name)',
            ));

            $final = $ztdPdo->query('SELECT id, name FROM merge_target ORDER BY id');
            self::assertNotFalse($final);
            self::assertSame([
                ['id' => 2, 'name' => 'inserted'],
                ['id' => 4, 'name' => 'prepared'],
                ['id' => 5, 'name' => 'cte'],
            ], $final->fetchAll(PDO::FETCH_ASSOC));

            $physical = $pdo->query(
                'SELECT (SELECT COUNT(*) FROM merge_target) + (SELECT COUNT(*) FROM merge_source)',
            );
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
