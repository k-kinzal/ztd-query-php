<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKeyName;

#[CoversClass(PrimaryKeyName::class)]
#[Small]
final class PrimaryKeyNameTest extends TestCase
{
    public function testAllocateUsesTheFirstVacantSuffixAndTruncatesAtACharacterBoundary(): void
    {
        $prefix = str_repeat('a', 55);
        $table = new Identifier($prefix . '漢字');
        $occupied = [$prefix . '漢_pkey' => true, $prefix . '_pkey1' => true];

        self::assertSame($prefix . '_pkey2', PrimaryKeyName::allocate($table, $occupied));
    }
}
