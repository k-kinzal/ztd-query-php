<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IntervalOperandOrder::class)]
#[Medium]
final class IntervalOperandOrderTest extends TestCase
{
    public function testBindsTheOperationAlternative(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT INTERVAL 1 DAY + CURRENT_DATE');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\DateShift::class, $shift);
        self::assertSame(IntervalOperandOrder::IntervalFirst, $shift->operandOrder);
    }
}
