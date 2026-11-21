<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;

#[CoversClass(Order::class)]
#[Small]
final class OrderTest extends TestCase
{
    public function testKeepsEffectiveDirectionAndNullPlacement(): void
    {
        self::assertTrue((new Order(1))->nullsFirst);
        self::assertFalse((new Order(1, true))->nullsFirst);
        self::assertTrue((new Order(2, true, true))->nullsFirst);
    }

    public function testZeroIsNotAnOutputOrdinal(): void
    {
        $this->expectExceptionMessage('order position must be positive');

        new Order(0);
    }
}
