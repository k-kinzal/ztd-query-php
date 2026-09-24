<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Serialization\Definition\Account\Identifications;

#[CoversClass(Identifications::class)]
#[Medium]
final class IdentificationsTest extends TestCase
{
    public function testWriteSpellsEachCredentialForm(): void
    {
        $secret = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'s'", 0));
        self::assertInstanceOf(Literal::class, $secret);
        self::assertSame("IDENTIFIED BY 's'", Identifications::write(new PasswordIdentification($secret))->toString());
        self::assertSame("IDENTIFIED BY PASSWORD 's'", Identifications::write(new HashIdentification($secret))->toString());
        self::assertSame('IDENTIFIED BY RANDOM PASSWORD', Identifications::write(RandomPassword::Generated)->toString());
        self::assertSame('IDENTIFIED WITH `p`', Identifications::write(new PluginIdentification('p'))->toString());
        self::assertSame("IDENTIFIED WITH `p` AS 's'", Identifications::write(new PluginHashIdentification('p', $secret))->toString());
        self::assertSame("IDENTIFIED WITH `p` BY 's'", Identifications::write(new PluginPasswordIdentification('p', $secret))->toString());
        self::assertSame('IDENTIFIED WITH `p` BY RANDOM PASSWORD', Identifications::write(new PluginRandomPasswordIdentification('p'))->toString());
    }

    public function testPluginQuotesTheName(): void
    {
        self::assertSame('`a``b`', Identifications::plugin('a`b')->toString());
    }

    public function testFactorWritesTheNumberAndKeyword(): void
    {
        self::assertSame('3 FACTOR', Identifications::factor(AuthenticationFactor::Third)->toString());
    }
}
