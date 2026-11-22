<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ResetQueryCache::class)]
#[Medium]
final class ResetQueryCacheTest extends TestCase
{
    public function testDeriveTargetRejectsReleasesWithoutAQueryCache(): void
    {
        $this->expectExceptionMessage('RESET QUERY CACHE needs MySQL 5.6 or 5.7.');

        new InspectedStatement((new Inspection())->context([]), new Reset([new ResetQueryCache()]));
    }

    public function testRenderWritesTheItem(): void
    {
        self::assertSame('RESET QUERY CACHE, MASTER', (new Inspection('mysql-5.6.51'))->inspect('reset query cache, master')->toString());
    }
}
