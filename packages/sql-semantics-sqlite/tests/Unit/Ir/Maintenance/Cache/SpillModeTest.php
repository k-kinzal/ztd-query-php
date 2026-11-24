<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode;

#[CoversClass(SpillMode::class)]
#[Small]
final class SpillModeTest extends TestCase
{
    public function testCasesUseTheNativeSwitches(): void
    {
        self::assertSame(['ON', 'OFF'], array_column(SpillMode::cases(), 'value'));
    }
}
