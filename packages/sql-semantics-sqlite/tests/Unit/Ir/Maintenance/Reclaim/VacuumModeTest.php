<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Reclaim;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\VacuumMode;

#[CoversClass(VacuumMode::class)]
#[Small]
final class VacuumModeTest extends TestCase
{
    public function testCasesMatchTheNativeCodes(): void
    {
        self::assertSame(['0', '1', '2'], array_column(VacuumMode::cases(), 'value'));
    }
}
