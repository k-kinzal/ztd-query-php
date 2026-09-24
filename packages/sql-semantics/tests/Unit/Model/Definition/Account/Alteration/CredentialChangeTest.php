<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CredentialChange::class)]
#[Medium]
final class CredentialChangeTest extends TestCase
{
    public function testKeepsTheReplacedPasswordAndRetentionForTheClientAccount(): void
    {
        $old = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'old'", 0));
        self::assertInstanceOf(Literal::class, $old);
        $change = new CredentialChange(ClientAccount::Connected, RandomPassword::Generated, $old, true);
        self::assertSame("'old'", $change->replacedPassword?->text);
        self::assertTrue($change->retainCurrentPassword);
        self::assertSame(RandomPassword::Generated, $change->identification);
    }

    public function testRejectsANumericReplacedPassword(): void
    {
        $number = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'NUM', '1', 0));
        $new = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'new'", 0));
        self::assertInstanceOf(Literal::class, $number);
        self::assertInstanceOf(Literal::class, $new);
        $this->expectException(InvalidStructure::class);
        new CredentialChange(ClientAccount::Connected, new PasswordIdentification($new), $number);
    }

    public function testRejectsAPluginForTheConnectingClientAccount(): void
    {
        $new = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'new'", 0));
        self::assertInstanceOf(Literal::class, $new);
        $this->expectException(InvalidStructure::class);
        new CredentialChange(ClientAccount::Connected, new PluginPasswordIdentification('p', $new));
    }
}
