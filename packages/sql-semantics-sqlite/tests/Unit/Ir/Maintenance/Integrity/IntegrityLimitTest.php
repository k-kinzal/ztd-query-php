<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Integrity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityLimit;

#[CoversClass(IntegrityLimit::class)]
#[Small]
final class IntegrityLimitTest extends TestCase
{
    public function testValueExposesTheNormalizedBudget(): void
    {
        self::assertSame(100, (new IntegrityLimit())->value);
        self::assertSame(2147483647, (new IntegrityLimit(2147483647))->value);
    }
}
