<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;

#[CoversClass(ResetQueryCache::class)]
#[Medium]
final class ResetQueryCacheTest extends TestCase
{
    public function testDeriveTargetRejectsReleasesWithoutAQueryCache(): void
    {
        $this->expectExceptionMessage('RESET QUERY CACHE needs MySQL 5.6 or 5.7.');

        (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql))->context([]), new Reset([new ResetQueryCache()]), new \SqlSemantics\Statement\Source\SourceMap());
    }

    public function testRenderWritesTheItem(): void
    {
        self::assertSame('RESET QUERY CACHE, MASTER', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('reset query cache, master')->toString());
    }
}
