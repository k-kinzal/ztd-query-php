<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;

#[CoversClass(KeyKind::class)]
final class KeyKindTest extends TestCase
{
    public function testSpellsTheKeywords(): void
    {
        self::assertSame(['INDEX', 'FOREIGN KEY', 'CHECK', 'CONSTRAINT'], array_map(static fn (KeyKind $kind): string => $kind->value, KeyKind::cases()));
    }
}
