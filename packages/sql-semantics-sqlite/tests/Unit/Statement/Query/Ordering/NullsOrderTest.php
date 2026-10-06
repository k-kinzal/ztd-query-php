<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;

#[CoversClass(NullsOrder::class)]
#[Small]
final class NullsOrderTest extends TestCase
{
    public function testCasesCarryTheKeywordsSqliteWrites(): void
    {
        self::assertSame(['FIRST', 'LAST'], array_map(static fn (NullsOrder $order): string => $order->value, NullsOrder::cases()));
        self::assertSame(NullsOrder::Last, NullsOrder::from('LAST'));
    }
}
