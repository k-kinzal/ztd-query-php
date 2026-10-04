<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\NativeSpatialCatalog;

#[CoversClass(NativeSpatialCatalog::class)]
#[Small]
final class NativeSpatialCatalogTest extends TestCase
{
    public function testRowsHoldAReleaseMaskArgumentCountsAndAResultCode(): void
    {
        self::assertCount(135, NativeSpatialCatalog::ROWS);
        self::assertSame([[3, 1, 1, 'DY'], [508, 1, 1, 'DY'], [508, 2, 2, 'GY']], NativeSpatialCatalog::ROWS['ST_X']);
        self::assertContainsOnly('array', NativeSpatialCatalog::ROWS);
    }
}
