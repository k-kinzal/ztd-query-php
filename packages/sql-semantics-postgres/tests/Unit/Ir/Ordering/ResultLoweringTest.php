<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\ProjectionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ResultLowering::class)]
#[Small]
final class ResultLoweringTest extends TestCase
{
    public function testTargetResolvesTheParsedOutputPosition(): void
    {
        $analyzed = (new Inspection())->inspect('SELECT 1 AS a, 2 AS b ORDER BY 2 DESC');
        $statement = $analyzed->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->options);
        $operation = $analyzed->binding();
        $fields = $operation->facts->query($statement)->fields();
        self::assertNotNull($fields);
        $binding = JoinBinding::bind($operation, $statement->from, true);
        $types = [];
        $results = (new ProjectionLowering())->lower($operation, $statement, $binding, true, $types, null, true);

        $target = (new ResultLowering())->target($statement->options->order[0], $operation, $fields, $binding, $results, true, false);

        self::assertSame(2, $target);
    }

    public function testOrdersKeepsTheInputPrecedence(): void
    {
        $analyzed = (new Inspection())->inspect('SELECT 1 AS a, 2 AS b ORDER BY (b), a');
        $statement = $analyzed->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertNotNull($statement->options);

        $orders = (new SelectLowering())->lower($analyzed->binding())->orderBy;

        self::assertSame(2, $orders[0]->target);
        self::assertSame(1, $orders[1]->target);
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
