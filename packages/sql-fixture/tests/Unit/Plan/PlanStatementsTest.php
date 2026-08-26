<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\PlanStatements;
use SqlFixture\Plan\PlanSyntaxException;

#[CoversClass(PlanStatements::class)]
#[UsesClass(PlanSyntaxException::class)]
final class PlanStatementsTest extends TestCase
{
    public function testOfSplitsOnCommasNewlinesAndSemicolons(): void
    {
        self::assertSame(
            ['a.id < b.a_id', 'c.id < d.c_id', 'e'],
            (new PlanStatements())->of("a.id < b.a_id,\nc.id < d.c_id; e"),
        );
    }

    public function testOfKeepsACommaInsideAGroupWithItsStatement(): void
    {
        self::assertSame(
            ['order.id < [a.order_id, b.order_id]'],
            (new PlanStatements())->of('order.id < [a.order_id, b.order_id]'),
        );
    }

    public function testOfKeepsACommaInsideACompositeEndpointWithItsStatement(): void
    {
        self::assertSame(
            ['order.(shop_id, no) < detail.(shop_id, order_no)'],
            (new PlanStatements())->of('order.(shop_id, no) < detail.(shop_id, order_no)'),
        );
    }

    public function testOfDropsWhatIsWrittenBetweenTwoSeparators(): void
    {
        self::assertSame(['order'], (new PlanStatements())->of("\n\n order ,, \n"));
    }

    public function testOfAnswersNothingForAPlanThatNamesNothing(): void
    {
        self::assertSame([], (new PlanStatements())->of("  \n , "));
    }

    public function testOfRefusesABracketClosedThatWasNeverOpened(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatements())->of('order.id < a.order_id]');
    }
}
