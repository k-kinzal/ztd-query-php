<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DeferredAggregate::class)]
#[Medium]
final class DeferredAggregateTest extends TestCase
{
    public function testDeriveScalarAndOperandsRetainTheRowDependency(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $call = new DeferredAggregate(M\Call\Aggregate\AggregateFunction::Sum, [$row]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        self::assertInstanceOf(Invalid::class, $call->deriveScalar($derivation, $derivation->environment())->type);
        self::assertSame([$row], $call->operands());
        self::assertTrue($row->hasBinding());
    }

    public function testOperandsRejectAnOrdinaryAggregate(): void
    {
        $this->expectExceptionMessage('A retained aggregate requires a non-scalar argument.');
        new DeferredAggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')]);
    }

    public function testProblemKeepsTheNestedOperandCondition(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new DeferredAggregate(M\Call\Aggregate\AggregateFunction::Count, [$row], true))->problem()->message());
    }

    public function testRenderRetainsQuantifiersAndDeferredArguments(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT SUM(ALL ROW(1,2))');
        self::assertSame('CREATE PROCEDURE p() SELECT SUM(ALL ROW(1, 2))', $operation->toString());
    }
}
