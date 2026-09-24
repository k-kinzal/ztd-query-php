<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\SeparatorText;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(SeparatorText::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SeparatorTextTest extends TestCase
{
    public function testBytesDecodesStringLiterals(): void
    {
        $literal = Expression::literal("a'b", Dialect::MySql);
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame("a'b", SeparatorText::bytes($literal));
    }

    public function testBytesRejectsNumbers(): void
    {
        $literal = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        SeparatorText::bytes($literal);
    }

    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(["'\\t\\\\'", "\t\\"])]
    #[TestWith(["'\\%'", '\\%'])]
    public function testTextDecodesQuotesAndEscapes(string $spelling, string $bytes): void
    {
        self::assertSame($bytes, SeparatorText::text($spelling));
    }

    public function testBitsPadsTheMostSignificantByte(): void
    {
        self::assertSame("\x01\x02", SeparatorText::bits('100000010'));
    }
}
