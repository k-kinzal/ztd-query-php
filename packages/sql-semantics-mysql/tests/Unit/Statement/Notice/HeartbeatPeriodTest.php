<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod;

#[CoversClass(HeartbeatPeriod::class)]
#[Small]
final class HeartbeatPeriodTest extends TestCase
{
    public function testCodeDistinguishesThePeriodConditions(): void
    {
        self::assertSame([1703, 1704, 1704], array_map(static fn (HeartbeatCondition $condition): int => (new HeartbeatPeriod($condition))->code(), HeartbeatCondition::cases()));
    }

    public function testMessageUsesTheResolvedTimeoutVocabulary(): void
    {
        self::assertStringContainsString('less than 1 millisecond', (new HeartbeatPeriod(HeartbeatCondition::BelowMinimum))->message());
        self::assertStringContainsString('slave_net_timeout', (new HeartbeatPeriod(HeartbeatCondition::AboveSlaveTimeout))->message());
        self::assertStringContainsString('replica_net_timeout', (new HeartbeatPeriod(HeartbeatCondition::AboveReplicaTimeout))->message());
    }
}
