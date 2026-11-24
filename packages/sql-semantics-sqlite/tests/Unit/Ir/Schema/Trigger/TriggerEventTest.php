<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent;

#[CoversClass(TriggerEvent::class)]
#[Small]
final class TriggerEventTest extends TestCase
{
    public function testCasesIdentifyTheNativeRoles(): void
    {
        self::assertSame(['INSERT', 'UPDATE', 'DELETE'], array_column(TriggerEvent::cases(), 'value'));
    }
}
