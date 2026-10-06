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
}
