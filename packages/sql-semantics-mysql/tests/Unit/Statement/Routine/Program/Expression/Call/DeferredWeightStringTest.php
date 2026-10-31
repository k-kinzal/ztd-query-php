<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightString;

#[CoversClass(DeferredWeightString::class)]
#[Medium]
final class DeferredWeightStringTest extends TestCase
{
    public function testDeriveScalarRetainsTheSubjectWithoutInventingAResult(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $call = new DeferredWeightString($row);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsPreservesTheSubjectIdentity(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame([$row], (new DeferredWeightString($row))->operands());
    }

    public function testProblemRetainsTheRequiredScalarWidth(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new DeferredWeightString($row))->problem()->message());
    }

    public function testRenderPreservesAllWeightOptionsInAStoredInstruction(): void
    {
        $sql = 'CREATE PROCEDURE p() DO WEIGHT_STRING(ROW(1, 2))';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $operation->toString());
    }
}
