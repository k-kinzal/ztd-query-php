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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTest;

#[CoversClass(DeferredNullTest::class)]
#[Medium]
final class DeferredNullTestTest extends TestCase
{
    public function testDeriveScalarPreservesTheDeferredCondition(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $value = new DeferredNullTest($operand);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsKeepsTheRetainedDependency(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        self::assertSame([$operand], (new DeferredNullTest($operand))->operands());
    }

    public function testProblemRetainsTheOwningConversion(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $problem = (new DeferredNullTest($operand))->problem();
        self::assertInstanceOf(TimeZoneConversion::class, $problem);
        self::assertSame($operand, $problem->expression);
    }

    public function testRenderRoundTripsTheCompleteOperator(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $value = new DeferredNullTest($operand);
        $cast = "CAST(1 AT TIME ZONE '+00:00' AS DATETIME)";
        $sql = $cast . ' IS NULL';
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() SELECT ' . $sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->statement->body);
        $item = $operation->statement->body->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $item);
        self::assertEquals($value, $item->expression);
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }
}
