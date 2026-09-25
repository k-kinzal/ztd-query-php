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
use SqlSemantics\Model\Query\Grouping\DescendingGroupKey;
use SqlSemantics\Model\Query\Grouping\Rollup;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;

#[CoversClass(DescendingGroupKey::class)]
#[Medium]
final class DescendingGroupKeyTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testKeepsTheDescendingKeysOfMySql5(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('SELECT a FROM t GROUP BY a DESC, b ASC WITH ROLLUP');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(Rollup::class, $statement->groupBy[0]);
        self::assertInstanceOf(DescendingGroupKey::class, $statement->groupBy[0]->keys[0]);
        self::assertInstanceOf(Expression::class, $statement->groupBy[0]->keys[1]);
        $written = (new SimpleSerializer())->serialize($statement);
        self::assertSame('SELECT `a` AS `a` FROM `t` GROUP BY `a` DESC, `b` WITH ROLLUP', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testRejectsADescendingKeyAfterMySql5(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a DESC');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DescendingGroupKey::class, $statement->groupBy[0]);
        $modern = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t GROUP BY a');
        self::assertInstanceOf(BoundSelect::class, $modern);
        $this->expectException(InvalidStructure::class);
        $modern->withGroupBy([$statement->groupBy[0]]);
    }
}
