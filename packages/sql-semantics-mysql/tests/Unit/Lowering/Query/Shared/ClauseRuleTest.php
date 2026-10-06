<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ClauseRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(ClauseRule::class)]
#[Medium]
final class ClauseRuleTest extends TestCase
{
    public function testPredicateLowersWhereHavingAndQualify(): void
    {
        self::assertSame('SELECT a FROM t WHERE a = 1 HAVING a > 2 QUALIFY a > 3', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a from t where a = 1 having a > 2 qualify a > 3')->toString());
        self::assertSame('SELECT a FROM t WHERE a = 1 HAVING a > 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t where a = 1 having a > 2')->toString());
    }

    public function testGroupingLowersEveryForm(): void
    {
        self::assertSame('SELECT a FROM t GROUP BY ROLLUP (a)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a from t group by rollup (a)')->toString());
        self::assertSame('SELECT a FROM t GROUP BY a ASC WITH ROLLUP', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t group by a asc with rollup')->toString());
    }

    public function testModifierLowersTheModifierAfterTheList(): void
    {
        self::assertSame('SELECT a FROM t GROUP BY a WITH CUBE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t group by a with cube')->toString());
    }

    public function testOrderingReadsIntegersAsPositions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t ORDER BY 1, a + 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[0]->expression);
        self::assertNotInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[1]->expression);
    }

    public function testOrderItemsKeepsIntegersAsWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a, ROW_NUMBER() OVER (ORDER BY 1) FROM t');

        self::assertSame('SELECT a, ROW_NUMBER() OVER (ORDER BY 1) FROM t', $operation->toString());
    }

    public function testItemsKeepsTheWrittenOrderAndDirections(): void
    {
        self::assertSame('SELECT a FROM t ORDER BY a DESC, b', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t order by a desc, b')->toString());
        self::assertSame('SELECT a FROM t ORDER BY a ASC, b DESC', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select a from t order by a asc, b desc')->toString());
    }

    public function testItemLowersOneOrderingItem(): void
    {
        self::assertSame('SELECT a FROM t GROUP BY a DESC', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t group by a desc')->toString());
    }

    public function testDirectionLowersAscendingAndDescending(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t ORDER BY a ASC, b DESC, c');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([Direction::Ascending, Direction::Descending, null], array_map(static fn (OrderItem $item): ?Direction => $item->direction, $operation->statement->orderBy));
    }

    public function testPositionsWrapsUnsignedIntegers(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $items = (new ClauseRule($lowering))->positions([new OrderItem(new NumberLiteral('2')), new OrderItem(new NumberLiteral('2.5'))]);

        self::assertInstanceOf(OutputOrdinal::class, $items[0]->expression);
        self::assertInstanceOf(NumberLiteral::class, $items[1]->expression);
    }

    public function testWindowsLowersTheNamedWindows(): void
    {
        self::assertSame('SELECT a FROM t WINDOW w AS (PARTITION BY a), v AS (w ORDER BY a)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a from t window w as (partition by a), v as (w order by a)')->toString());
    }
}
