<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Evaluation\Subquery\SingleRow;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleRow::class)]
#[Small]
final class SingleRowTest extends TestCase
{
    public function testDomainsKeepStringReadsQuietAndNumericDomainsUnchanged(): void
    {
        $integer = Domain::integer();
        $string = Domain::string(10, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'));
        $row = new SingleRow(new Rows(new QueryPlan(new ZeroRows(2), [$integer, $string], ['a', 'b'])));

        self::assertEquals([$integer, $string->withQuiet()], $row->domains());
    }

    public function testReadCachesOnlyWithinTheStatementContext(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $row = new SingleRow(new Rows(new QueryPlan(new Inline([[new ColumnRead(Domain::integer(), 0, 1)]], 1), [Domain::integer()], ['a'])), true);
        $frame = new Frame($context, [10]);
        $first = $row->read($frame);
        $frame->row = [20];
        $again = $row->read($frame);
        $fresh = $row->read(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1.0), [20]));

        self::assertSame([[10], [10], [20]], [$first, $again, $fresh]);
    }

    public function testFetchRefusesMoreThanOneRow(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1242);

        $session->query('SELECT (1,2) = (SELECT 1,2 UNION ALL SELECT 3,4)');
    }

    public function testFetchKeepsNullExpressionsReadableWhenTheSubqueryIsEmpty(): void
    {
        $result = (new Instance())->connect()->query('SELECT (NULL,NULL) <=> (SELECT NULL,NULL), (NULL,NULL) <=> (SELECT NULL,NULL WHERE 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testValueRetainsTheAggregateStateAfterItsOnlyRowIsSkipped(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT COUNT(*),MAX(1) FROM DUAL LIMIT 0) <=> (0,NULL), (SELECT COUNT(*),MAX(1) FROM DUAL LIMIT 0 OFFSET 1) <=> (0,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }
}
