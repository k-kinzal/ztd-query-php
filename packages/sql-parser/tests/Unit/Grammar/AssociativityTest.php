<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\Associativity;

#[CoversClass(Associativity::class)]
#[Small]
final class AssociativityTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['Left', 'Right', 'NonAssoc', 'Precedence'], array_map(static fn (Associativity $case): string => $case->name, Associativity::cases()));
    }
}
