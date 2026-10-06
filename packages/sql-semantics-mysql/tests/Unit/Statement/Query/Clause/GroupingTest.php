<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Grouping::class)]
#[Medium]
final class GroupingTest extends TestCase
{
    public function testRenderWritesTheItemsAndTheModifier(): void
    {
        self::assertSame('SELECT a FROM t GROUP BY a, b WITH ROLLUP', (new Semantics(Dialect::MySql))->analyze('select a from t group by a, b with rollup')->toString());
        self::assertSame('SELECT a FROM t GROUP BY ROLLUP (a, b)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a from t group by rollup (a, b)')->toString());
        self::assertSame('SELECT a FROM t GROUP BY CUBE (a)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a from t group by cube (a)')->toString());
        self::assertSame('SELECT a FROM t GROUP BY a DESC WITH CUBE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t group by a desc with cube')->toString());
    }

    public function testItemsKeepTheirWrittenOrderAndDirection(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT a FROM t GROUP BY b DESC, a');

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
