<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(TextLiteralLowering::class)]
#[Small]
final class TextLiteralLoweringTest extends TestCase
{
    public function testLowerConcatenatesPlainDecodedSegmentsInOrder(): void
    {
        $result = (new TextLiteralLowering())->lower(new StringLiteral(['a', "\0b"]));

        self::assertInstanceOf(TextLiteral::class, $result);
        self::assertSame("a\0b", $result->value);
    }

    public function testLowerLeavesASeparateScalarRoleToItsBinder(): void
    {
        self::assertNull((new TextLiteralLowering())->lower(new NumberLiteral('1')));
    }

    public function testLowerRejectsANationalString(): void
    {
        $this->expectExceptionMessage('A text literal requires the plain utf8mb4 projection role.');

        (new TextLiteralLowering())->lower(new StringLiteral(['text'], national: true));
    }
}
