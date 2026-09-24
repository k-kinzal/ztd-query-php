<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Collation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;

#[CoversClass(CollationProvider::class)]
final class CollationProviderTest extends TestCase
{
    public function testSpellsTheProviderNames(): void
    {
        self::assertSame(['libc', 'icu', 'builtin'], array_map(static fn (CollationProvider $provider): string => $provider->value, CollationProvider::cases()));
    }
}
