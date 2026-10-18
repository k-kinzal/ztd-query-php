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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWeightParameters;

#[CoversClass(DeferredWeightParameters::class)]
#[Medium]
final class DeferredWeightParametersTest extends TestCase
{
    public function testDeriveScalarRetainsTheSubjectWithoutInventingAResult(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $call = new DeferredWeightParameters($row, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('64'));
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsPreservesTheSubjectIdentity(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame([$row], (new DeferredWeightParameters($row, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('64')))->operands());
    }

    public function testProblemRetainsTheRequiredScalarWidth(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new DeferredWeightParameters($row, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('64')))->problem()->message());
    }

    public function testRenderPreservesAllWeightOptionsInAStoredInstruction(): void
    {
        $sql = 'CREATE PROCEDURE p() DO WEIGHT_STRING(ROW(1, 2), 7, 2, 64)';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $operation->toString());
    }
}
