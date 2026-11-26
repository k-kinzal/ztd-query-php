<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Grouping::class)]
#[Medium]
final class GroupingTest extends TestCase
{
    public function testRenderWritesTheItemsAndTheModifier(): void
    {
        self::assertSame('SELECT a FROM t GROUP BY a, b WITH ROLLUP', (new Inspection())->inspect('select a from t group by a, b with rollup')->toString());
        self::assertSame('SELECT a FROM t GROUP BY ROLLUP (a, b)', (new Inspection('mysql-9.1.0'))->inspect('select a from t group by rollup (a, b)')->toString());
        self::assertSame('SELECT a FROM t GROUP BY CUBE (a)', (new Inspection('mysql-9.1.0'))->inspect('select a from t group by cube (a)')->toString());
        self::assertSame('SELECT a FROM t GROUP BY a DESC WITH CUBE', (new Inspection('mysql-5.6.51'))->inspect('select a from t group by a desc with cube')->toString());
    }

    public function testItemsKeepTheirWrittenOrderAndDirection(): void
    {
        $operation = (new Inspection('mysql-5.7.44'))->inspect('SELECT a FROM t GROUP BY b DESC, a');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNotNull($operation->statement->groupBy);
        self::assertSame(Direction::Descending, $operation->statement->groupBy->items[0]->direction);
        self::assertNull($operation->statement->groupBy->items[1]->direction);
        self::assertNull($operation->statement->groupBy->modifier);
    }

    public function testAnEmptyGroupingIsRejected(): void
    {
        $this->expectExceptionMessage('GROUP BY holds at least one grouping expression.');

        new Grouping([], GroupingModifier::WithRollup);
    }
}
