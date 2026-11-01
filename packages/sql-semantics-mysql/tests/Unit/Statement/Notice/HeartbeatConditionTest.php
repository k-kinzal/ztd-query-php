<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod;

#[CoversClass(HeartbeatCondition::class)]
#[Small]
final class HeartbeatConditionTest extends TestCase
{
    public function testCasesRemainDistinctOccurrencesWhenRepeated(): void
    {
        $first = new HeartbeatPeriod(HeartbeatCondition::AboveReplicaTimeout);
        $second = new HeartbeatPeriod(HeartbeatCondition::AboveReplicaTimeout);

        self::assertSame($first->condition, $second->condition);
        self::assertNotSame($first, $second);
    }
}
