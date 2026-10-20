<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\MeasuredTable;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasuredTable::class)]
#[Small]
final class MeasuredTableTest extends TestCase
{
    public function testReportMeasuresEmptyAndPopulatedIterators(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $empty = $session->query('EXPLAIN ANALYZE TABLE t')[0];
        $session->query('INSERT INTO t VALUES(1),(2),(3)');
        $populated = $session->query('EXPLAIN ANALYZE TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $empty);
        self::assertInstanceOf(ResultSet::class, $populated);
        self::assertMatchesRegularExpression('/cost=0 rows=0.*actual time=[0-9.e+-]+\.\.[0-9.e+-]+ rows=0 loops=1/', $empty->rows[0][0] ?? '');
        self::assertMatchesRegularExpression('/cost=0 rows=3.*actual time=[0-9.e+-]+\.\.[0-9.e+-]+ rows=3 loops=1/', $populated->rows[0][0] ?? '');
        self::assertCount(3, $session->instance->dictionary->table('d', 't')?->data->rows ?? []);
    }

    public function testReportLeavesOrdinaryExplainUnmeasured(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $reply = $session->query('EXPLAIN FORMAT=TREE TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([["-> Table scan on t\n"]], $reply->rows);
    }
}
