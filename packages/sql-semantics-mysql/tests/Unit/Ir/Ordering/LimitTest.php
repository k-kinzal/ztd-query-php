<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\Limit;

#[CoversClass(Limit::class)]
#[Small]
final class LimitTest extends TestCase
{
    public function testKeepsCountAndOffsetInTheirRoles(): void
    {
        $limit = new Limit(3, 2);
        self::assertSame(3, $limit->count);
        self::assertSame(2, $limit->offset);
    }

    public function testRejectsOutOfRangeOffsets(): void
    {
        $this->expectExceptionMessage('row offset must be between');

        new Limit(2, 2147483648);
    }
}
