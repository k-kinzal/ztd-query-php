<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNot;

#[CoversClass(DeferredNot::class)]
#[Medium]
final class DeferredNotTest extends TestCase
{
    public function testDeriveScalarPreservesTheDeferredCondition(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $value = new DeferredNot($operand);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsKeepsTheRetainedDependency(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        self::assertSame([$operand], (new DeferredNot($operand))->operands());
    }

    public function testProblemRetainsTheOwningConversion(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $problem = (new DeferredNot($operand))->problem();
        self::assertInstanceOf(TimeZoneConversion::class, $problem);
        self::assertSame($operand, $problem->expression);
    }

    public function testRenderRoundTripsTheCompleteOperator(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $value = new DeferredNot($operand);
        $cast = "CAST(1 AT TIME ZONE '+00:00' AS DATETIME)";
        $sql = 'NOT ' . $cast;
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() SELECT ' . $sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->body);
        $item = $operation->body->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $item);
        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($value, $item->expression));
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }
}
