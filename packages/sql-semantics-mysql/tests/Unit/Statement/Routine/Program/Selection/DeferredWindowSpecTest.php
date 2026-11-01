<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;

#[CoversClass(DeferredWindowSpec::class)]
#[Medium]
final class DeferredWindowSpecTest extends TestCase
{
    public function testOperandRetainsTheFirstNonScalarItem(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $window = new DeferredWindowSpec(order: [new M\Routine\Program\Selection\DeferredOrderItem($row)]);
        self::assertSame($row, $window->operand());
    }

    public function testDeriveWindowKeepsEveryOriginalDependency(): void
    {
        $value = new M\Literal\NumberLiteral('1');
        $row = new M\Expression\Row([$value, new M\Literal\NumberLiteral('2')]);
        $window = new DeferredWindowSpec(order: [new M\Routine\Program\Selection\DeferredOrderItem($row)]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $window->deriveWindow($derivation, $derivation->environment());
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $value->scalarInformation()->nullability);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderPreservesTheCompleteStoredWindow(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT 1 WINDOW w AS (ORDER BY (1, 2) DESC)';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }
}
