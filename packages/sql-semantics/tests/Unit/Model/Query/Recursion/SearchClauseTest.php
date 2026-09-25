<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Recursion\SearchClause;
use SqlSemantics\Model\Query\Recursion\SearchOrder;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(SearchClause::class)]
#[Medium]
final class SearchClauseTest extends TestCase
{
    public function testKeepsTheSearchClauseThroughStructuralSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r WHERE n < 3) SEARCH BREADTH FIRST BY n SET ord SELECT * FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $search = $statement->ctes?->definitions[0]->search;
        self::assertInstanceOf(SearchClause::class, $search);
        self::assertSame(SearchOrder::BreadthFirst, $search->order);
        self::assertSame(['n'], $search->columns);
        self::assertSame(['n', 'ord'], array_map(static fn ($output): ?string => $output->name, $statement->outputs));
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('WITH RECURSIVE "r"("n") AS (SELECT 1 UNION ALL SELECT ("n" + 1) FROM "r" WHERE ("n" < 3)) SEARCH BREADTH FIRST BY "n" SET "ord" SELECT "r"."n" AS "n", "r"."ord" AS "ord" FROM "r"', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testRejectsAnEmptyColumnList(): void
    {
        $this->expectException(InvalidStructure::class);
        new SearchClause(SearchOrder::DepthFirst, [], 'ord');
    }

    public function testRejectsAnUnnamedSequenceColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new SearchClause(SearchOrder::DepthFirst, ['n'], '');
    }
}
