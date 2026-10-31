<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Plan\Path\SetCount::class)]
#[Small]
final class SetCountTest extends TestCase
{
    public function testCasesDistinguishUncountedMaterializedAndRawOperandRows(): void
    {
        self::assertSame(['None', 'Materialized', 'RightInput'], array_column(\MySqlMemory\Plan\Path\SetCount::cases(), 'name'));
    }

}
