<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCachePages;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize;

#[CoversClass(DefaultCacheSize::class)]
#[Small]
final class DefaultCacheSizeTest extends TestCase
{
    public function testToStringEmitsTheSelectedPagerAndCookie(): void
    {
        self::assertSame('PRAGMA "main".default_cache_size', (new DefaultCacheSize())->toString());
        $value = new DefaultCacheSize(new Identifier('archive'), new DefaultCachePages(70));
        self::assertSame('PRAGMA "archive".default_cache_size(70)', $value->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new DefaultCacheSize())->profileId());
    }
}
