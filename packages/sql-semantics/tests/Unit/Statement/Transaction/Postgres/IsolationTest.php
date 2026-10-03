<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\Isolation;

#[CoversClass(Isolation::class)]
#[Small]
final class IsolationTest extends TestCase
{
    public function testCasesRetainsEveryRequestedLevelIncludingReadUncommitted(): void
    {
        self::assertSame(['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], array_map(static fn (Isolation $level): string => $level->value, Isolation::cases()));
    }
}
