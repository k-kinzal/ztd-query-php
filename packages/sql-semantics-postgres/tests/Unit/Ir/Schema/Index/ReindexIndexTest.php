<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ReindexIndex::class)]
#[Small]
final class ReindexIndexTest extends TestCase
{
    public function testToStringKeepsAConcurrentRequestOnATemporaryTarget(): void
    {
        $index = new IndexReference(new Identifier('pg_temp'), new Identifier('idx"quoted'), new Identifier('orders'));
        $value = new ReindexIndex($index, IndexRebuildMode::Concurrent);

        self::assertSame('REINDEX INDEX CONCURRENTLY "pg_temp"."idx""quoted"', $value->toString());
    }

    public function testProfileIdRetainsTheSelectedRelease(): void
    {
        $index = new IndexReference(new Identifier('app'), new Identifier('idx'), new Identifier('orders'));
        $value = new ReindexIndex($index, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testToStringRebuildsTheExactExclusionIndex(): void
    {
        $index = new ExclusionIndexReference(new Identifier('app'), new Identifier('over"lap'), new Identifier('orders'));
        $value = new ReindexIndex($index);

        self::assertSame('REINDEX INDEX "app"."over""lap"', $value->toString());
    }
}
