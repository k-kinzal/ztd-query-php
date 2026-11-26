<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Testing\Sqlite\Inspection;

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
        $operation = (new Inspection())->inspect('SELECT a FROM `a b`, [c d], "e f"');

        self::assertSame('SELECT a FROM `a b`, `c d`, `e f`', $operation->toString());
    }
}
