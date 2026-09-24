<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(PluginHashIdentification::class)]
#[Medium]
final class PluginHashIdentificationTest extends TestCase
{
    public function testKeepsTheSuppliedOperands(): void
    {
        $literal = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'x'", 0));
        self::assertInstanceOf(Literal::class, $literal);
        $identification = new PluginHashIdentification('mysql_native_password', $literal);
        self::assertSame(['mysql_native_password', "'x'"], [$identification->plugin, $identification->hash->text]);
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $number = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '7', 0));
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new PluginHashIdentification('mysql_native_password', $number);
    }
}
