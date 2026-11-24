<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize;

#[CoversClass(CacheSize::class)]
#[Small]
final class CacheSizeTest extends TestCase
{
    public function testToStringPreservesZeroAsAnExplicitWrite(): void
    {
        self::assertSame('PRAGMA "main".cache_size', (new CacheSize())->toString());
        self::assertSame('PRAGMA "archive".cache_size(0)', (new CacheSize(new Identifier('archive'), new CacheAmount(0)))->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new CacheSize())->profileId());
    }
}
