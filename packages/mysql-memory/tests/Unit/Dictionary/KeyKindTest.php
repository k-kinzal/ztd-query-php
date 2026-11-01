<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\KeyKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyKind::class)]
#[Small]
final class KeyKindTest extends TestCase
{
    public function testCasesNameEveryKindOfIndex(): void
    {
        self::assertSame(['Primary', 'Unique', 'Index', 'FullText', 'Spatial'], array_map(static fn (KeyKind $kind): string => $kind->name, KeyKind::cases()));
    }
}
