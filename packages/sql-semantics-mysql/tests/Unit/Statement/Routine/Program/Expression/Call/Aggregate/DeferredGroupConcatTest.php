<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcat;

#[CoversClass(DeferredGroupConcat::class)]
#[Medium]
final class DeferredGroupConcatTest extends TestCase
{
    public function testDeriveScalarRetainsAStoredCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT GROUP_CONCAT(RANK() OVER ())');
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderPreservesArgumentsAndModifiers(): void
    {
        $sql = "CREATE PROCEDURE p() SELECT GROUP_CONCAT(DISTINCT RANK() OVER () ORDER BY 1 SEPARATOR '|')";
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }

    public function testOperandsRetainsEveryOrderingDependency(): void
    {
        $rank = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::Rank, [], new M\Call\Window\WindowSpec());
        $one = new M\Literal\NumberLiteral('1');
        $call = new DeferredGroupConcat([$rank], order: [new M\Query\OrderItem($one)]);
        self::assertSame([$rank, $one], $call->operands());
    }

    public function testProblemRetainsTheOffendingWindow(): void
    {
        $rank = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::Rank, [], new M\Call\Window\WindowSpec());
        self::assertSame($rank, (new DeferredGroupConcat([$rank]))->problem()->call);
    }
}
