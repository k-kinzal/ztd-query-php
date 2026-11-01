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
        self::assertCount(230, NativeCatalog::ROWS);
        self::assertSame([[511, 1, -1, 'SY']], NativeCatalog::ROWS['CONCAT']);
        self::assertContainsOnly('array', NativeCatalog::ROWS);
    }

    public function testRowsKeyTheNullabilityOfGtidSubsetOnTheRelease(): void
    {
        self::assertSame([[3, 2, 2, 'IN'], [508, 2, 2, 'IP']], NativeCatalog::ROWS['GTID_SUBSET']);
    }
}
