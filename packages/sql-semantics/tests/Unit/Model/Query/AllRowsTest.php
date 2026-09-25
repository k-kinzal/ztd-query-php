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
use SqlSemantics\Model\Query\AllRows;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AllRows::class)]
#[Medium]
final class AllRowsTest extends TestCase
{
    #[TestWith(['SELECT id FROM t'])]
    #[TestWith(['SELECT ALL id FROM t'])]
    public function testKeepsEveryRowWhetherAllIsSpelledOrImplied(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(AllRows::class, $statement->quantifier);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testIsNotAppliedToADistinctProjection(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT DISTINCT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertNotInstanceOf(AllRows::class, $statement->quantifier);
    }
}
