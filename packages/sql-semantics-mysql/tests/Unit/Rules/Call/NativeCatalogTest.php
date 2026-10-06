<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\NativeCatalog;

#[CoversClass(NativeCatalog::class)]
#[Small]
final class NativeCatalogTest extends TestCase
{
    public function testRowsHoldAReleaseMaskArgumentCountsAndAResultCode(): void
    {
        self::assertCount(232, NativeCatalog::ROWS);
        self::assertSame([[511, 1, -1, 'SP']], NativeCatalog::ROWS['CONCAT']);
        self::assertContainsOnly('array', NativeCatalog::ROWS);
    }
}
