<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

#[CoversClass(ResultLowering::class)]
#[Small]
final class ResultLoweringTest extends TestCase
{
    public function testOrderResolvesTheParsedOutputPosition(): void
    {
        $ordered = (new ResultLowering())->order(new OrderItem(new OutputOrdinal(new NumberLiteral('2')), Direction::Descending));

        self::assertSame(2, $ordered->position);
        self::assertTrue($ordered->descending);
    }

    public function testLimitKeepsTheDifferentOperandRoles(): void
    {
        $limit = (new ResultLowering())->limit(new RowLimit(new NumberLiteral('2'), new NumberLiteral('3'), OffsetSpelling::Comma));

        self::assertNotNull($limit);
        self::assertSame(2, $limit->count);
        self::assertSame(3, $limit->offset);
    }

    public function testBoundResolvesTheLiteralInteger(): void
    {
        self::assertSame(2, (new ResultLowering())->bound(new NumberLiteral('2')));
    }
}
