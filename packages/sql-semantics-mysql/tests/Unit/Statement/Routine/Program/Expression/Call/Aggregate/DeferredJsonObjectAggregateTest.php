<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate;

#[CoversClass(DeferredJsonObjectAggregate::class)]
#[Medium]
final class DeferredJsonObjectAggregateTest extends TestCase
{
    public function testDeriveScalarRetainsAnInvocationCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() SELECT JSON_OBJECTAGG('k', SUM(1))");
        self::assertSame([], $operation->diagnostics());
    }

    public function testOperandsPreservesBothArguments(): void
    {
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, []);
        $one = new M\Literal\NumberLiteral('1');
        self::assertSame([$count, $one], (new DeferredJsonObjectAggregate($count, $one))->operands());
    }

    public function testProblemRetainsTheNestedAggregate(): void
    {
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, []);
        self::assertSame($count, (new DeferredJsonObjectAggregate($count, new M\Literal\NumberLiteral('1')))->problem()->call);
    }

    public function testRenderPreservesBothQuantifiers(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT JSON_OBJECTAGG(ALL COUNT(*), ALL 1)';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }
}
