<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\ShiftDirection;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShiftDirection::class)]
#[Medium]
final class ShiftDirectionTest extends TestCase
{
    public function testBindsTheOperationAlternative(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE - INTERVAL 1 DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\DateShift::class, $shift);
        self::assertSame(ShiftDirection::Subtract, $shift->direction);
    }
}
