<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Symbols\Associativity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Associativity::class)]
#[Small]
final class AssociativityTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['left', 'right', 'nonassoc', 'precedence'], array_map(static fn (Associativity $case): string => $case->value, Associativity::cases()));
    }
}
