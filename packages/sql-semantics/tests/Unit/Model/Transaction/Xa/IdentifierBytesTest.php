<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Transaction\Xa\IdentifierBytes;

#[CoversClass(IdentifierBytes::class)]
#[Medium]
final class IdentifierBytesTest extends TestCase
{
    #[DataProvider('providerLiteralLengths')]
    public function testLengthCountsBytesRatherThanSqlSpelling(string $sql, int $length): void
    {
        $token = (new DialectParser(Dialect::MySql))->parse('SELECT ' . $sql)->tokens()[1];
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame($length, IdentifierBytes::length($literal));
        IdentifierBytes::check($literal);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerLiteralLengths(): iterable
    {
        yield 'empty' => ["''", 0];
        yield 'doubled quote' => ["'a''b'", 3];
        yield 'backslash quote' => ["'a\\'b'", 3];
        yield 'escaped newline' => ["'a\\nb'", 3];
        yield 'literal escape in pattern' => ["'\\%\\_'", 4];
        yield 'double quoted' => ['"a""b"', 3];
        yield 'multibyte' => ["'日本'", 6];
        yield 'hexadecimal' => ["X'00ff'", 2];
        yield 'hexadecimal odd digits' => ['0x123', 2];
        yield 'empty hex' => ["X''", 0];
        yield 'bit string' => ["B'001000010'", 2];
        yield 'bit number' => ['0b100', 1];
        yield 'one full bit byte' => ["B'00100001'", 1];
        yield 'two doubled quotes' => ["'a''''b'", 4];
        yield 'quotes around a letter' => ["'''a'''", 3];
        yield 'maximum byte count' => ["'" . str_repeat('a', 64) . "'", 64];
    }

    public function testCheckRejectsNumericLiterals(): void
    {
        $token = (new DialectParser(Dialect::MySql))->parse('SELECT 42')->tokens()[1];
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        IdentifierBytes::check($literal);
    }

    public function testCheckRejectsForeignDialectLiterals(): void
    {
        $token = (new DialectParser(Dialect::PostgreSql))->parse("SELECT 'x'")->tokens()[1];
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($token);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        IdentifierBytes::check($literal);
    }

    public function testCheckRejectsOversizedMultibyteLiterals(): void
    {
        $token = (new DialectParser(Dialect::MySql))->parse("SELECT '" . str_repeat('日', 22) . "'")->tokens()[1];
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        IdentifierBytes::check($literal);
    }

    public function testLengthRejectsANationalStringLiteral(): void
    {
        $token = (new DialectParser(Dialect::MySql))->parse("SELECT N'ab'")->tokens()[1];
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        IdentifierBytes::length($literal);
    }
}
