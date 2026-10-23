<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

#[CoversClass(DeferredCast::class)]
#[Medium]
final class DeferredCastTest extends TestCase
{
    public function testDeriveScalarRetainsTheConstraintUntilInvocation(): void
    {
        $value = new DeferredCast(new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00')), new CastTarget(CastKind::Signed));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsRetainsTheConversionDependency(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        self::assertSame([$operand], (new DeferredCast($operand, new CastTarget(CastKind::Char)))->operands());
    }

    public function testProblemRetainsTheOwningTimeZoneConversion(): void
    {
        $operand = new DeferredTimeZone(new NumberLiteral('1'), new Text('+00:00'));
        $problem = (new DeferredCast($operand, new CastTarget(CastKind::Char)))->problem();
        self::assertInstanceOf(TimeZoneConversion::class, $problem);
        self::assertSame($operand, $problem->expression);
    }

    public function testRenderKeepsBothConversionRoles(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = "CREATE PROCEDURE p() SELECT CAST(CAST(1 AT TIME ZONE '+00:00' AS DATETIME) AS CHAR)";
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $operation->statement->body);
        $item = $operation->statement->body->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $item);
        self::assertInstanceOf(DeferredCast::class, $item->expression);
        self::assertSame(CastKind::Char, $item->expression->target->kind);
        self::assertSame($sql, $operation->toString());
        self::assertSame($sql, $semantics->analyze($operation->toString())->toString());
    }
}
