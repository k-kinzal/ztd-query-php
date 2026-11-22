<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;

#[CoversClass(ResultLowering::class)]
#[Small]
final class ResultLoweringTest extends TestCase
{
    public function testOrderResolvesTheParsedOutputPosition(): void
    {
        $ordered = (new ResultLowering())->order(new SortItem(new OutputPosition(new Constant(new IntegerConstant('2')), OrderingClause::OrderBy), SortDirection::Descending));

        self::assertSame(2, $ordered->position);
        self::assertTrue($ordered->descending);
    }

    public function testLimitKeepsTheDifferentOperandRoles(): void
    {
        $limit = (new ResultLowering())->limit(new RowLimit(new LimitCount(new Constant(new IntegerConstant('2'))), new Offset(new Constant(new IntegerConstant('3')))));

        self::assertNotNull($limit);
        self::assertSame(2, $limit->count);
        self::assertSame(3, $limit->offset);
    }

    public function testBoundResolvesTheLiteralInteger(): void
    {
        self::assertSame(2, (new ResultLowering())->bound(new Constant(new IntegerConstant('2'))));
    }
}
