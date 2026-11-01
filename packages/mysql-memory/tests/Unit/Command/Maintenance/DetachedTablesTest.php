<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Maintenance;

use MySqlMemory\Command\Maintenance\DetachedTables;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DetachedTables::class)]
#[Small]
final class DetachedTablesTest extends TestCase
{
    public function testReportClosesAHandlerAfterCheckingItsDetachedFile(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $reply = $session->query('CHECK TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['d.t', 'check', 'Error', "Tablespace has been discarded for table 't'"], ['d.t', 'check', 'error', 'Corrupt']], $reply->rows);
        self::assertNotContains(['d', 't'], $session->instance->dictionary->cache->names());
    }

    public function testAnalyzeReportsTheDetachedPartitionOnBothStatisticsReads(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $reply = $session->query('ANALYZE TABLE p')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame(['Warning', 'Warning', 'status'], array_column($reply->rows, 2));
        self::assertSame($reply->rows[0], $reply->rows[1]);
        self::assertStringContainsString('/* Partition `p0` */', $reply->rows[0][3] ?? '');
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testOptimizeRetainsTheRecreationFailureAsDiagnostics(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ALTER TABLE t DISCARD TABLESPACE');
        $reply = $session->query('OPTIMIZE TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame(3, $reply->warnings);
        self::assertSame('Got error -2 from storage engine', $reply->rows[1][3]);
        self::assertSame([1814, 1814, 1030], array_column($session->diagnostics->conditions, 1));
    }
}
