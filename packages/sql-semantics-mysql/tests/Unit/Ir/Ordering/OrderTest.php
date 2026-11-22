<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\Order;

#[CoversClass(Order::class)]
#[Small]
final class OrderTest extends TestCase
{
    public function testRequiresPositiveOutputPositions(): void
    {
        $this->expectExceptionMessage('order position must be positive');

        new Order(0);
    }

    public function testUsesTheNativeDefaultNullPlacement(): void
    {
        self::assertTrue((new Order(1))->nullsFirst);
    }
}
