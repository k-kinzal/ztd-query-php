<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;

#[CoversClass(TruthTest::class)]
#[Small]
final class TruthTestTest extends TestCase
{
    public function testNestedTruthTestRetainsTheExactChild(): void
    {
        $inner = new TruthTest(TruthOperator::IsUnknown, new NullLiteral());
        $outer = new TruthTest(TruthOperator::IsNotFalse, $inner);

        self::assertSame($inner, $outer->operand);
        self::assertSame(TruthOperator::IsNotFalse, $outer->operator);
    }
}
