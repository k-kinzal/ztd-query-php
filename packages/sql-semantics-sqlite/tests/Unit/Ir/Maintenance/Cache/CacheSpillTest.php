<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold;

#[CoversClass(CacheSpill::class)]
#[Small]
final class CacheSpillTest extends TestCase
{
    public function testToStringDistinguishesTheReadSwitchAndJointChange(): void
    {
        $schema = new Identifier('archive');

        self::assertSame('PRAGMA "main".cache_spill', (new CacheSpill())->toString());
        self::assertSame('PRAGMA "archive".cache_spill(OFF)', (new CacheSpill($schema, SpillMode::Disabled))->toString());
        $threshold = new CacheSpill($schema, new SpillThreshold(new CacheAmount(256), SpillMode::Enabled));
        self::assertSame("PRAGMA \"archive\".cache_spill('+256')", $threshold->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new CacheSpill())->profileId());
    }
}
