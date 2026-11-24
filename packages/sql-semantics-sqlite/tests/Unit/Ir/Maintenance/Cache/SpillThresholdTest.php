<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold;

#[CoversClass(SpillThreshold::class)]
#[Small]
final class SpillThresholdTest extends TestCase
{
    public function testSqlArgumentPreservesTheFlagAndTheSelectedThreshold(): void
    {
        $pages = new CacheAmount(256);

        self::assertSame("'+256'", (new SpillThreshold($pages, SpillMode::Enabled))->sqlArgument());
        self::assertSame('256', (new SpillThreshold($pages, SpillMode::Disabled))->sqlArgument());
        self::assertSame('-128', (new SpillThreshold(new CacheAmount(-128), SpillMode::Enabled))->sqlArgument());
    }
}
