<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DeferredValuesQuery::class)]
#[Medium]
final class DeferredValuesQueryTest extends TestCase
{
    public function testDeriveQueryRetainsEveryColumnAndInvalidOperandType(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $query = new DeferredValuesQuery([new M\Query\With\DeferredValueRow([new M\Routine\Program\Expression\Call\DeferredChar([$row]), new M\Literal\NumberLiteral('7')])]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $query->deriveQuery($derivation, $derivation->environment());
        self::assertSame(['column_0', 'column_1'], array_map(static fn ($slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertInstanceOf(Invalid::class, $fact->shape->slots[0]->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testProblemRetainsAnEarlierRowWidthBeforeTheDeferredOperand(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $query = new DeferredValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')]), new M\Query\With\DeferredValueRow([$row, new M\Literal\NumberLiteral('7')])]);
        self::assertInstanceOf(M\Query\Problem\CountMismatch::class, $query->problem());
    }

    public function testRenderRetainsAllSetOperandsAndTheirRows(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (TABLE t EXCEPT VALUES ROW(1), ROW(CHAR((SELECT *)))) SELECT 9');
        self::assertSame('WITH c AS (TABLE t EXCEPT VALUES ROW(1), ROW(CHAR((SELECT *)))) SELECT 9', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
