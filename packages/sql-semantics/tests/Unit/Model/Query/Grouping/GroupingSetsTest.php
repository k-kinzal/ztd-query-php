<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Grouping\Cube;
use SqlSemantics\Model\Query\Grouping\EmptyGroupingSet;
use SqlSemantics\Model\Query\Grouping\GroupingSets;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(GroupingSets::class)]
#[Medium]
final class GroupingSetsTest extends TestCase
{
    public function testKeepsNestedGroupingSetsAndDistinct(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)'));
        $statement = $binder->bind('SELECT a FROM t GROUP BY DISTINCT GROUPING SETS ((a), (), CUBE(b), GROUPING SETS(a, b))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertTrue($statement->distinctGroupingSets);
        self::assertInstanceOf(GroupingSets::class, $statement->groupBy[0]);
        self::assertCount(4, $statement->groupBy[0]->sets);
        self::assertInstanceOf(EmptyGroupingSet::class, $statement->groupBy[0]->sets[1]);
        self::assertInstanceOf(Cube::class, $statement->groupBy[0]->sets[2]);
        self::assertInstanceOf(GroupingSets::class, $statement->groupBy[0]->sets[3]);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('SELECT "a" AS "a" FROM "public"."t" GROUP BY DISTINCT GROUPING SETS("a", (), CUBE("b"), GROUPING SETS("a", "b"))', $written);
        $rebound = $binder->bind($written);
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertTrue($rebound->distinctGroupingSets);
        self::assertSame($written, (new SimpleSerializer())->serialize($rebound));
    }

    public function testRejectsAnEmptyElementList(): void
    {
        $this->expectException(InvalidStructure::class);
        new GroupingSets([]);
    }
}
