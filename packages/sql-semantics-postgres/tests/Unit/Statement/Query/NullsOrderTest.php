<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;

#[CoversClass(NullsOrder::class)]
#[Small]
final class NullsOrderTest extends TestCase
{
    public function testCasesSpellThePlaces(): void
    {
        self::assertSame(['FIRST', 'LAST'], array_map(static fn (NullsOrder $order): string => $order->value, NullsOrder::cases()));
    }
}
