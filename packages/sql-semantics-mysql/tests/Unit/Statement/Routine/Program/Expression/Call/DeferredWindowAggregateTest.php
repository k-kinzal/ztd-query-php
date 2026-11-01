<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window as W;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate;

#[CoversClass(DeferredWindowAggregate::class)]
#[Medium]
final class DeferredWindowAggregateTest extends TestCase
{
    public function testDeriveScalarPreservesTheFrameDiagnosticWithoutAnOrdinaryScalar(): void
    {
        $window = new W\WindowSpec(frame: new W\Frame(W\FrameUnit::Groups, new W\FrameBound(W\FrameBoundKind::CurrentRow)));
        $value = new DeferredWindowAggregate(new Aggregate(AggregateFunction::Count, []), $window);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertCount(1, $derivation->facts()->diagnostics);
    }

    public function testOperandsKeepsTheOriginalArgumentIdentity(): void
    {
        $argument = new NumberLiteral('7');
        $window = new W\WindowSpec(frame: new W\Frame(W\FrameUnit::Groups, new W\FrameBound(W\FrameBoundKind::CurrentRow)));
        $value = new DeferredWindowAggregate(new Aggregate(AggregateFunction::Sum, [$argument]), $window);
        self::assertSame([$argument], $value->operands());
    }

    public function testProblemRetainsTheWindowThatOwnsItsCondition(): void
    {
        $window = new W\WindowSpec(frame: new W\Frame(W\FrameUnit::Groups, new W\FrameBound(W\FrameBoundKind::CurrentRow)));
        $value = new DeferredWindowAggregate(new Aggregate(AggregateFunction::Count, []), $window);
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing::class, $problem);
        self::assertSame($window, $problem->expression);
    }

    public function testRenderPreservesTheStoredInstructionAndUnusedCommonTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'CREATE PROCEDURE p() SELECT SUM(a) OVER (GROUPS CURRENT ROW) FROM t';
        $operation = $semantics->analyze($sql);
        self::assertSame($sql, $operation->toString());
        self::assertSame($sql, $semantics->analyze($operation->toString())->toString());
        $unused = 'WITH c AS (SELECT SUM(a) OVER (GROUPS CURRENT ROW) FROM t) SELECT 1';
        self::assertSame($unused, $semantics->analyze($unused)->toString());
    }
}
