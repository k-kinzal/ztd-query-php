<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability;

#[CoversClass(Writability::class)]
#[Small]
final class WritabilityTest extends TestCase
{
    public function testCasesNameWhatSetCanChange(): void
    {
        self::assertSame(['Writable', 'GlobalOnly', 'ReadOnly'], array_map(static fn (Writability $writability): string => $writability->name, Writability::cases()));
    }
}
