<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\Associativity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Associativity::class)]
#[Small]
final class AssociativityTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['left', 'right', 'nonassoc'], array_map(static fn (Associativity $case): string => $case->value, Associativity::cases()));
    }
}
