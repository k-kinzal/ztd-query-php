<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;

#[CoversClass(BetweenTest::class)]
#[Small]
final class BetweenTestTest extends TestCase
{
    public function testRetainsTheThreeOrderedRolesAndNegation(): void
    {
        $operand = new Parameter('2');
        $lower = new IntegerLiteral('4');
        $upper = new IntegerLiteral('1');

        $range = new BetweenTest($operand, $lower, $upper, true);

        self::assertSame($operand, $range->operand);
        self::assertSame($lower, $range->lower);
        self::assertSame($upper, $range->upper);
        self::assertTrue($range->negated);
    }
}
