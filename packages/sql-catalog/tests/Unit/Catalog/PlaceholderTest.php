<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\ValueDomain;

#[CoversClass(Placeholder::class)]
#[UsesClass(ValueDomain::class)]
final class PlaceholderTest extends TestCase
{
    public function testKeyIsTheNameOrThePosition(): void
    {
        self::assertSame('id', (new Placeholder(':id', 0, 'id', null))->key());
        self::assertSame('2', (new Placeholder('?', 2, null, null))->key());
    }

    public function testWithValueKeepsEverythingElse(): void
    {
        $value = new ValueDomain('int', [1], true, []);
        $bound = (new Placeholder(':id', 0, 'id', null))->withValue($value);
        self::assertSame($value, $bound->value);
        self::assertSame(':id', $bound->token);
        self::assertSame(0, $bound->position);
        self::assertSame('id', $bound->name);
    }
}
