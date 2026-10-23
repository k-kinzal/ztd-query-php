<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\InsertGrouping;

#[CoversClass(InsertGrouping::class)]
#[Small]
final class InsertGroupingTest extends TestCase
{
    public function testWindowNameDistinguishesTheAmbientEvaluationRole(): void
    {
        $plain = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')]);
        $window = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')], over: new M\Call\Window\WindowSpec());
        self::assertNull((new InsertGrouping($plain))->windowName());
        self::assertSame('sum', (new InsertGrouping($window))->windowName());
    }

    public function testMessageDescribesTheRetainedGroupingCondition(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec());
        $condition = new InsertGrouping($call);
        self::assertSame($call, $condition->function);
        self::assertSame('A VALUES element cannot evaluate a window outside a query block.', $condition->message());
    }
}
