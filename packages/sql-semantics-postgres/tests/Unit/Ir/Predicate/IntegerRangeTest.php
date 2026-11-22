<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;

#[CoversClass(IntegerRange::class)]
#[Small]
final class IntegerRangeTest extends TestCase
{
    public function testRangePreservesAllThreeOperandRoles(): void
    {
        $subject = new ColumnReference(2, 1);
        $lower = new IntegerLiteral('7');
        $upper = new NullLiteral();
        $value = new IntegerRange($subject, $lower, $upper, negated: true);

        self::assertSame($subject, $value->subject);
        self::assertSame($lower, $value->lower);
        self::assertSame($upper, $value->upper);
        self::assertTrue($value->negated);
        self::assertFalse($value->symmetric);
    }
}
