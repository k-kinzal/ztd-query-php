<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Grouping\Rollup;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(Rollup::class)]
#[Medium]
final class RollupTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testKeepsMySqlWithRollupThroughStructuralSerialization(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('SELECT a, b, COUNT(*) FROM t GROUP BY a, b WITH ROLLUP');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Rollup::class, $statement->groupBy[0]);
        self::assertCount(2, $statement->groupBy[0]->keys);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('SELECT `a` AS `a`, `b` AS `b`, count(*) FROM `t` GROUP BY `a`, `b` WITH ROLLUP', $written);
        $rebound = $binder->bind($written);
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertInstanceOf(Rollup::class, $rebound->groupBy[0]);
    }

    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testReadsTheMySqlRollupFunctionSpelling(string $release): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT a FROM t GROUP BY ROLLUP(a, b)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Rollup::class, $statement->groupBy[0]);
        self::assertSame('SELECT `a` AS `a` FROM `t` GROUP BY `a`, `b` WITH ROLLUP', (new SimpleSerializer())->serialize($statement));
    }

    public function testKeepsPostgreSqlRollupAmongOtherKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('SELECT a, b FROM t GROUP BY a, ROLLUP(a, (a, b))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]);
        self::assertInstanceOf(Rollup::class, $statement->groupBy[1]);
        self::assertSame('SELECT "a" AS "a", "b" AS "b" FROM "public"."t" GROUP BY "a", ROLLUP("a", ROW("a", "b"))', (new SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnEmptyKeyList(): void
    {
        $this->expectException(InvalidStructure::class);
        new Rollup([]);
    }
}
