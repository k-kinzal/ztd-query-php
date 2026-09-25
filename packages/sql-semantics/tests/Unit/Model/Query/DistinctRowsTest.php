<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\DistinctRows;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DistinctRows::class)]
#[Medium]
final class DistinctRowsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT DISTINCT "id" AS "id" FROM "public"."t"'])]
    #[TestWith([Dialect::MySql, 'SELECT DISTINCT `id` AS `id` FROM `t`'])]
    #[TestWith([Dialect::Sqlite, 'SELECT DISTINCT "id" AS "id" FROM "main"."t"'])]
    public function testEliminatesDuplicatesInEveryDialect(Dialect $dialect, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT DISTINCT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DistinctRows::class, $statement->quantifier);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
