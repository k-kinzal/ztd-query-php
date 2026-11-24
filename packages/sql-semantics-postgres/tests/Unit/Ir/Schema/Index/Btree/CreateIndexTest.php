<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateIndex::class)]
#[Small]
final class CreateIndexTest extends TestCase
{
    public function testToStringRetainsRepeatedKeyAndCoveringOccurrences(): void
    {
        $table = new TableSource(new Identifier('app'), new Identifier('orders'), [new Identifier('id'), new Identifier('other')]);
        $key = new IndexKey(0, IndexOrder::DescendingNullsLast);
        $cover = new IncludedColumn(0);
        $index = new CreateIndex($table, new Identifier('odd"name'), [&$key, new IndexKey(0)], [&$cover, $cover], nullsNotDistinct: true);
        $key = new IndexKey(1);
        $cover = new IncludedColumn(1);

        self::assertSame(
            'CREATE INDEX "odd""name" ON "app"."orders" USING btree ("id" DESC NULLS LAST, "id" ASC NULLS LAST) INCLUDE ("id", "id") NULLS NOT DISTINCT',
            $index->toString(),
        );
    }

    public function testProfileIdReportsTheExactGrammarRelease(): void
    {
        $table = new TableSource(new Identifier('pg_temp'), new Identifier('orders'), [new Identifier('id')]);
        $index = new CreateIndex($table, new Identifier('ix'), [new IndexKey(0)], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $index->profileId());
    }
}
