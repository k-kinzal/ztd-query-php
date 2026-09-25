<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Grouping\EmptyGroupingSet;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(EmptyGroupingSet::class)]
#[Medium]
final class EmptyGroupingSetTest extends TestCase
{
    public function testKeepsTheGrandTotalGroup(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('SELECT count(*) FROM t GROUP BY ()');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(EmptyGroupingSet::class, $statement->groupBy[0]);
        self::assertSame('SELECT "count"(*) FROM "public"."t" GROUP BY ()', (new SimpleSerializer())->serialize($statement));
    }
}
