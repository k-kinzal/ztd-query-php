<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Sql\PlaceholderRef;

#[CoversClass(PlaceholderRef::class)]
final class PlaceholderRefTest extends TestCase
{
    public function testIsPositionalWhenThereIsNoName(): void
    {
        self::assertTrue((new PlaceholderRef('?', 0))->isPositional());
        self::assertFalse((new PlaceholderRef(':id', 0, 'id'))->isPositional());
    }

    public function testKeyIsTheNameOrThePosition(): void
    {
        self::assertSame('id', (new PlaceholderRef(':id', 3, 'id'))->key());
        self::assertSame('3', (new PlaceholderRef('?', 3))->key());
    }
}
