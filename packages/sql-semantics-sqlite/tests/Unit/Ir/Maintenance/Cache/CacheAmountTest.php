<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount;

#[CoversClass(CacheAmount::class)]
#[Small]
final class CacheAmountTest extends TestCase
{
    public function testKeepsTheSignedNativeEndpoints(): void
    {
        self::assertSame(-2147483648, (new CacheAmount(-2147483648))->value);
        self::assertSame(2147483647, (new CacheAmount(2147483647))->value);
    }
}
