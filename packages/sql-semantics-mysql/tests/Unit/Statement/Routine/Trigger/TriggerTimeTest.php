<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;

#[CoversClass(TriggerTime::class)]
#[Small]
final class TriggerTimeTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['BEFORE', 'AFTER'], array_map(static fn (TriggerTime $time): string => $time->value, TriggerTime::cases()));
    }
}
