<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(IdentificationOperands::class)]
#[Medium]
final class IdentificationOperandsTest extends TestCase
{
    public function testSecretAcceptsAMySqlTextLiteral(): void
    {
        $text = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'secret'", 0));
        self::assertInstanceOf(Literal::class, $text);
        IdentificationOperands::secret($text);
        self::assertSame("'secret'", $text->text);
    }

    public function testSecretRejectsAHexadecimalLiteral(): void
    {
        $binary = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'HEX_NUM', '0x2A', 0));
        self::assertInstanceOf(Literal::class, $binary);
        $this->expectException(InvalidStructure::class);
        IdentificationOperands::secret($binary);
    }

    public function testSecretRejectsAnotherDialectText(): void
    {
        $text = (new LiteralBinder(Dialect::PostgreSql))->bind(new Token(0, 'SCONST', "'secret'", 0));
        self::assertInstanceOf(Literal::class, $text);
        $this->expectException(InvalidStructure::class);
        IdentificationOperands::secret($text);
    }

    public function testHashAcceptsAHexadecimalAuthenticationString(): void
    {
        $binary = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'HEX_NUM', '0x2A', 0));
        self::assertInstanceOf(Literal::class, $binary);
        IdentificationOperands::hash($binary);
        self::assertSame('0x2A', $binary->text);
    }

    public function testHashRejectsADecimalNumber(): void
    {
        $number = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '42', 0));
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        IdentificationOperands::hash($number);
    }

    public function testPluginRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        IdentificationOperands::plugin('');
    }
}
