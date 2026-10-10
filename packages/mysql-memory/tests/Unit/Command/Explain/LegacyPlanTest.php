<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\ExplainCommand;
use MySqlMemory\Command\Explain\LegacyPlan;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(LegacyPlan::class)]
#[Small]
final class LegacyPlanTest extends TestCase
{
    public function testRowsKeepsTheTableAndDmlKindIn57(): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1),(2)');
        $result = $session->query('EXPLAIN DELETE FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([3, 10, 12], [$result->columns[0]->length, $result->columns[9]->length, count($result->columns)]);
        self::assertSame([['1', 'DELETE', 't', null, 'ALL', null, null, null, null, '2', '100.00', 'Deleting all rows']], $result->rows);
    }

    public function testHeadingsSelectsTheColumnsOfEachLegacyModifier(): void
    {
        $headings = (new ExplainCommand())->headings();
        $plain = LegacyPlan::headings($headings, null);
        $extended = LegacyPlan::headings($headings, ExplainModifier::Extended);
        $partitions = LegacyPlan::headings($headings, ExplainModifier::Partitions);

        self::assertSame(['id', 'select_type', 'table', 'type', 'possible_keys', 'key', 'key_len', 'ref', 'rows', 'Extra'], array_map(static fn (Heading $heading): string => $heading->name, $plain));
        self::assertSame([3, 10], [$plain[0]->length, $plain[8]->length]);
        self::assertSame('filtered', $extended[9]->name);
        self::assertSame('partitions', $partitions[3]->name);
    }

    public function testRowsDescribesUnrestrictedDeletionInTenColumns(): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1),(2)');
        $result = $session->query('EXPLAIN DELETE FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'SIMPLE', null, null, null, null, null, null, '2', 'Deleting all rows']], $result->rows);
    }
}
