<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\SeparatorText;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

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

    #[TestWith(["x'2C'", ','])]
    #[TestWith(["X'0a0B'", "\n\x0b"])]
    #[TestWith(['0x2c', ','])]
    #[TestWith(['0xA', "\n"])]
    #[TestWith(['0xABC', "\x0a\xbc"])]
    #[TestWith(["b'100000001'", "\x01\x01"])]
    #[TestWith(["B'1'", "\x01"])]
    #[TestWith(['0b100000001', "\x01\x01"])]
    #[TestWith(['0b11111111', "\xff"])]
    #[TestWith(["'a'", 'a'])]
    #[TestWith(['"b"', 'b'])]
    public function testBytesDecodesEachSeparatorSpelling(string $spelling, string $bytes): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT ' . $spelling);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $literal = $statement->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame($bytes, SeparatorText::bytes($literal));
    }

    public function testBytesRejectsNationalStrings(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT N'x'");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $literal = $statement->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        SeparatorText::bytes($literal);
    }

    public function testBytesRejectsLiteralsOfOtherDialects(): void
    {
        $literal = Expression::literal('a', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        SeparatorText::bytes($literal);
    }

    #[TestWith(["'a\\'", 'a\\'])]
    #[TestWith(["'\\0\\n\\r\\b\\t\\Z\\_\\x'", "\0\n\r\x08\t\x1a\\_x"])]
    #[TestWith(["''''", "'"])]
    public function testTextDecodesTrailingBackslashesAndEveryEscape(string $spelling, string $bytes): void
    {
        self::assertSame($bytes, SeparatorText::text($spelling));
    }

    public function testBitsKeepsAFullByteUnpadded(): void
    {
        self::assertSame("\xff", SeparatorText::bits('11111111'));
    }
}
