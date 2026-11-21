<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\NumericLiteralLowering;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;

#[CoversClass(NumericLiteralLowering::class)]
#[Small]
final class NumericLiteralLoweringTest extends TestCase
{
    public function testLowerUsesTheBoundaryInTheWrittenDirection(): void
    {
        $sameDigits = new ParsedInteger('9223372036854775808');
        $lower = new NumericLiteralLowering();

        self::assertEquals(new IntegerLiteral('-9223372036854775808'), $lower->lower($sameDigits, true));
        self::assertEquals(new RealLiteral('9223372036854775808', null), $lower->lower($sameDigits, false));
    }
}
