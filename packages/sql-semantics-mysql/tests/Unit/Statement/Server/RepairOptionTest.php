<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\RepairOption;

#[CoversClass(RepairOption::class)]
#[Small]
final class RepairOptionTest extends TestCase
{
    public function testCasesSpellEveryRepairOption(): void
    {
        self::assertSame(['QUICK', 'EXTENDED', 'USE_FRM'], array_column(RepairOption::cases(), 'value'));
    }
}
