<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(PluginPasswordIdentification::class)]
#[Medium]
final class PluginPasswordIdentificationTest extends TestCase
{
    public function testKeepsTheSuppliedOperands(): void
    {
        $literal = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'x'", 0));
        self::assertInstanceOf(Literal::class, $literal);
        $identification = new PluginPasswordIdentification('caching_sha2_password', $literal);
        self::assertSame(['caching_sha2_password', "'x'"], [$identification->plugin, $identification->password->text]);
    }

    public function testRejectsAnInvalidOperand(): void
    {
        $number = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '7', 0));
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new PluginPasswordIdentification('caching_sha2_password', $number);
    }
}
