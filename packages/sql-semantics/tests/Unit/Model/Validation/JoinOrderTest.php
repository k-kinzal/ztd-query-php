<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\JoinOrder;
use SqlSemantics\SchemaBuilder;

#[CoversClass(JoinOrder::class)]
#[Medium]
final class JoinOrderTest extends TestCase
{
    public function testStraightAcceptsAMySqlStraightJoinAndPlainJoins(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)')))->bind('SELECT 1 FROM t JOIN u ON t.a = u.a STRAIGHT_JOIN t AS v');
        self::assertInstanceOf(BoundSelect::class, $statement);
        JoinOrder::straight($statement->from, Dialect::MySql);
        JoinOrder::straight(null, Dialect::PostgreSql);
        self::assertSame('SELECT 1 FROM(`t` INNER JOIN `u` ON (`t`.`a` = `u`.`a`)) STRAIGHT_JOIN `t` AS `v`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testStraightRejectsAStraightJoinOutsideMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)', 'CREATE TABLE u (a INT)')))->bind('SELECT 1 FROM t JOIN u STRAIGHT_JOIN t AS v ON TRUE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('STRAIGHT_JOIN requires MySQL.');
        JoinOrder::straight($statement->from, Dialect::Sqlite);
    }
}
