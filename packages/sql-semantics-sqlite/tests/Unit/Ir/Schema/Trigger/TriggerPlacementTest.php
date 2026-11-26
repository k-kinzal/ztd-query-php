<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerPlacement;

#[CoversClass(TriggerPlacement::class)]
#[Small]
final class TriggerPlacementTest extends TestCase
{
    public function testCasesIdentifyTheNativeRoles(): void
    {
        self::assertSame(['stored', 'named-temporary', 'temporary-keyword'], array_column(TriggerPlacement::cases(), 'value'));
    }
}
