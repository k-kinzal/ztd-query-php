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
use SqlSemantics\Model\Query\Grouping\Cube;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(Cube::class)]
#[Medium]
final class CubeTest extends TestCase
{
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testKeepsMySqlCubeThroughStructuralSerialization(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('SELECT a FROM t GROUP BY CUBE(a, b)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Cube::class, $statement->groupBy[0]);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('SELECT `a` AS `a` FROM `t` GROUP BY CUBE(`a`, `b`)', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testKeepsPostgreSqlCube(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('SELECT a FROM t GROUP BY CUBE(a, b)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Cube::class, $statement->groupBy[0]);
        self::assertCount(2, $statement->groupBy[0]->keys);
    }

    public function testRejectsAnEmptyKeyList(): void
    {
        $this->expectException(InvalidStructure::class);
        new Cube([]);
    }
}
