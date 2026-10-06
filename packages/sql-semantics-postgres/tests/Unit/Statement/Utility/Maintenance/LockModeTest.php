<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode;

#[CoversClass(LockMode::class)]
#[Small]
final class LockModeTest extends TestCase
{
    public function testCasesAreTheEightModesFromWeakestToStrongest(): void
    {
        self::assertSame(
            ['ACCESS SHARE', 'ROW SHARE', 'ROW EXCLUSIVE', 'SHARE UPDATE EXCLUSIVE', 'SHARE', 'SHARE ROW EXCLUSIVE', 'EXCLUSIVE', 'ACCESS EXCLUSIVE'],
            array_map(static fn (LockMode $mode): string => $mode->value, LockMode::cases()),
        );
    }
}
