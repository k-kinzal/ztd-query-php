<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Storage\IndexStatistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexStatistics::class)]
#[Medium]
final class IndexStatisticsTest extends TestCase
{
    public function testSampleUsesIndexComparisonAndKeepsNullAsOneValue(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(v VARCHAR(64) COLLATE utf8mb4_unicode_520_ci,KEY k(v)); INSERT INTO t VALUES ('a'),(_utf8mb4 X'612000'),(NULL),('a')");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $sample = IndexStatistics::sample($table);

        self::assertSame([4, ['k' => 2]], [$sample->rows, $sample->cardinalities]);
    }

    public function testGroupsUsesTheObservedSmallTableCrossover(): void
    {
        self::assertFalse((new IndexStatistics(4, ['v' => 2]))->groups('v'));
        self::assertTrue((new IndexStatistics(5, ['v' => 2]))->groups('v'));
        self::assertFalse((new IndexStatistics(5, []))->groups('v'));
    }

    public function testRefreshRunsAfterMaintenanceAndRetainsTheEarlierRowCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(v INT); INSERT INTO t VALUES(1),(1); ALTER TABLE t ADD KEY(v)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertNotNull($table->indexStatistics);
        self::assertSame(2, $table->indexStatistics->rows);
        $session->query('INSERT INTO t VALUES(2)');
        self::assertSame(2, $table->indexStatistics->rows);
        $session->query('ANALYZE TABLE t');
        self::assertSame(3, $table->indexStatistics->rows);
        $session->query('TRUNCATE TABLE t');
        self::assertNull($table->indexStatistics);
    }
}
