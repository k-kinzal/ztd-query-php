<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Name::class)]
#[Medium]
final class NameTest extends TestCase
{
    public function testValueIsTheDecodedIdentifier(): void
    {
        self::assertSame('order items', (new Name('order items'))->value);
    }

    public function testValueIsDecodedFromEveryQuotingStyleOfTheProfile(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM `a b`, [c d], "e f"');

        self::assertSame('SELECT a FROM `a b`, `c d`, `e f`', $operation->toString());
    }
    public function testQuotedNameKeepsItsDecodedValueAndDelimiter(): void
    {
        $name = new Name('a`b', \SqlSemantics\Statement\Identifier\NameQuote::Backtick);
        self::assertSame('a`b', $name->value);
        self::assertSame(\SqlSemantics\Statement\Identifier\NameQuote::Backtick, $name->quote);
    }

    public function testBareNameCannotInjectTokens(): void
    {
        $this->expectExceptionMessage('A bare identifier contains only identifier characters.');
        new Name('a; SELECT 1', \SqlSemantics\Statement\Identifier\NameQuote::Bare);
    }

}
