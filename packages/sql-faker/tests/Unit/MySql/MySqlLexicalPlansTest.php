<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\MySql\MySqlLexicalPlans;

#[CoversClass(MySqlLexicalPlans::class)]
#[UsesClass(GenerationPlan::class)]
final class MySqlLexicalPlansTest extends TestCase
{
    public function testQuotedIdentifierAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('quoted_identifier', MySqlLexicalPlans::quotedIdentifier(2, 8)->lexicalTarget());
    }

    public function testStringLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('string_literal', MySqlLexicalPlans::stringLiteral(2, 8)->lexicalTarget());
    }

    public function testNationalStringLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('national_string_literal', MySqlLexicalPlans::nationalStringLiteral(2, 8)->lexicalTarget());
    }

    public function testDollarQuotedStringAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('dollar_quoted_string', MySqlLexicalPlans::dollarQuotedString(2, 8)->lexicalTarget());
    }

    public function testIntegerLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('integer_literal', MySqlLexicalPlans::integerLiteral(2, 8)->lexicalTarget());
    }

    public function testLongIntegerLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('long_integer_literal', MySqlLexicalPlans::longIntegerLiteral(2, 8)->lexicalTarget());
    }

    public function testUnsignedBigIntLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('unsigned_big_int_literal', MySqlLexicalPlans::unsignedBigIntLiteral(2, 8)->lexicalTarget());
    }

    public function testDecimalLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('decimal_literal', MySqlLexicalPlans::decimalLiteral(8, 2)->lexicalTarget());
    }

    public function testFloatLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('float_literal', MySqlLexicalPlans::floatLiteral(8, 2, -3, 3)->lexicalTarget());
    }

    public function testHexLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('hex_literal', MySqlLexicalPlans::hexLiteral(2, 8)->lexicalTarget());
    }

    public function testQuotedHexLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('quoted_hex_literal', MySqlLexicalPlans::quotedHexLiteral(2, 8)->lexicalTarget());
    }

    public function testBinaryLiteralAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('binary_literal', MySqlLexicalPlans::binaryLiteral(2, 8)->lexicalTarget());
    }

    public function testHostnameAsksForTheLexemeItIsNamedAfter(): void
    {
        self::assertSame('hostname', MySqlLexicalPlans::hostname(2, 8, 12)->lexicalTarget());
    }
}
