<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerTiming;

#[CoversClass(TriggerTiming::class)]
#[Small]
final class TriggerTimingTest extends TestCase
{
    public function testCasesIdentifyTheNativeRoles(): void
    {
        self::assertSame(['AFTER', 'BEFORE', 'INSTEAD OF'], array_column(TriggerTiming::cases(), 'value'));
    }
}
