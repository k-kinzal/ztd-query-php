<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\NativeInternalCatalog;

#[CoversClass(NativeInternalCatalog::class)]
#[Small]
final class NativeInternalCatalogTest extends TestCase
{
    public function testRowsHoldAReleaseMaskArgumentCountsAndAResultCode(): void
    {
        self::assertCount(57, NativeInternalCatalog::ROWS);
        self::assertSame([[1020, 8, 9, 'IY']], NativeInternalCatalog::ROWS['INTERNAL_TABLE_ROWS']);
        self::assertContainsOnly('array', NativeInternalCatalog::ROWS);
    }
}
