<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;

#[CoversClass(IntegerDistinctness::class)]
#[Small]
final class IntegerDistinctnessTest extends TestCase
{
    public function testNullAndReferenceOccupyTheirOwnPositions(): void
    {
        $left = new NullLiteral();
        $right = new ColumnReference(2, 1);
        $test = new IntegerDistinctness($left, $right, true);

        self::assertSame($left, $test->left);
        self::assertSame($right, $test->right);
        self::assertTrue($test->negated);
    }
}
