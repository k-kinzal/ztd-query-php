<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Recursion\CycleClause;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(CycleClause::class)]
#[Medium]
final class CycleClauseTest extends TestCase
{
    public function testKeepsTheCycleClauseAndItsMarkValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n % 3 + 1 FROM r) CYCLE n SET looped TO 'Y' DEFAULT 'N' USING path SELECT n, looped FROM r");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $cycle = $statement->ctes?->definitions[0]->cycle;
        self::assertInstanceOf(CycleClause::class, $cycle);
        self::assertSame(['n'], $cycle->columns);
        self::assertSame(['looped', 'path'], [$cycle->markColumn, $cycle->pathColumn]);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('WITH RECURSIVE "r"("n") AS (SELECT 1 UNION ALL SELECT (("n" % 3) + 1) FROM "r") CYCLE "n" SET "looped" TO \'Y\' DEFAULT \'N\' USING "path" SELECT "n" AS "n", "looped" AS "looped" FROM "r"', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testRejectsOneMarkValueWithoutTheOther(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CycleClause(['n'], 'c', $statement->outputs[0]->expression, null, 'p');
    }

    public function testRejectsUnnamedAddedColumns(): void
    {
        $this->expectException(InvalidStructure::class);
        new CycleClause(['n'], 'c', null, null, '');
    }
}
