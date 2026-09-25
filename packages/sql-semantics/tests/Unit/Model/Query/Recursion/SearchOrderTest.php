<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Recursion\SearchOrder;

#[CoversClass(SearchOrder::class)]
#[Medium]
final class SearchOrderTest extends TestCase
{
    public function testSpellsBothOrders(): void
    {
        self::assertSame(['BREADTH FIRST', 'DEPTH FIRST'], array_map(static fn (SearchOrder $order): string => $order->value, SearchOrder::cases()));
    }
}
