<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CachePragmaLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(CachePragmaLowering::class)]
#[Small]
final class CachePragmaLoweringTest extends TestCase
{
    public function testLowerPublishesEachNativeCacheOperation(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(CacheSize::class, $semantics->analyze('PRAGMA cache_size'));
        self::assertInstanceOf(DefaultCacheSize::class, $semantics->analyze('PRAGMA default_cache_size'));
        self::assertInstanceOf(CacheSpill::class, $semantics->analyze('PRAGMA cache_spill'));
    }

    public function testCapacityKeepsTheDecodedSignedOperand(): void
    {
        $ir = (new Semantics())->analyze("PRAGMA temp.cache_size('-128KiB')");

        self::assertInstanceOf(CacheSize::class, $ir);
        self::assertSame('temp', $ir->schema->value);
        self::assertSame(-128, $ir->amount?->value);
    }

    public function testPersistentSaturatesTheNativeSignedMinimum(): void
    {
        $ir = (new Semantics())->analyze('PRAGMA default_cache_size(-2147483648)');

        self::assertInstanceOf(DefaultCacheSize::class, $ir);
        self::assertSame(2147483647, $ir->capacity?->pages);
    }

    public function testSpillingKeepsLowByteAndSignedFallbackDistinct(): void
    {
        $semantics = new Semantics();
        $unsigned = $semantics->analyze('PRAGMA cache_spill(+256)');
        $signed = $semantics->analyze("PRAGMA cache_spill('+256')");

        self::assertInstanceOf(CacheSpill::class, $unsigned);
        self::assertInstanceOf(CacheSpill::class, $signed);
        self::assertInstanceOf(SpillThreshold::class, $unsigned->change);
        self::assertInstanceOf(SpillThreshold::class, $signed->change);
        self::assertSame(SpillMode::Disabled, $unsigned->change->mode);
        self::assertSame(SpillMode::Enabled, $signed->change->mode);
    }
}
