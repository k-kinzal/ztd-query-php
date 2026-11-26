<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange;

#[CoversClass(FlagChange::class)]
#[Small]
final class FlagChangeTest extends TestCase
{
    public function testNativeValues(): void
    {
        self::assertSame(['0', '1', 'RESET'], array_column(FlagChange::cases(), 'value'));
    }
}
