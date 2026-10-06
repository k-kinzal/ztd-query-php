<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::class)]
#[Small]
final class SearchOrderTest extends TestCase
{
    public function testOrdersAreSpelled(): void
    {
        self::assertSame(['DEPTH', 'BREADTH'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder $order): string => $order->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::cases()));
    }
}
