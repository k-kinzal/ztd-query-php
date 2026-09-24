<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\Relation\Joining\CrossJoin;
use SqlSemantics\Model\Relation\Joining\NaturalJoin;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\Model\Relation\Joining\UnconditionalOuterJoin;
use SqlSemantics\Model\Relation\Joining\UsingJoin;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\Joins;

#[CoversClass(Joins::class)]
#[Medium]
final class JoinsTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'SELECT t.id FROM t JOIN s USING (id)', '`t` INNER JOIN `s` USING(`id`)', UsingJoin::class])]
    #[TestWith([Dialect::MySql, 'SELECT t.id FROM t NATURAL LEFT JOIN s', '`t` NATURAL LEFT JOIN `s`', NaturalJoin::class])]
    #[TestWith([Dialect::MySql, 'SELECT t.id FROM t NATURAL JOIN s', '`t` NATURAL JOIN `s`', NaturalJoin::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT t.id FROM t NATURAL INNER JOIN s', '"public"."t" NATURAL JOIN "public"."s"', NaturalJoin::class])]
    #[TestWith([Dialect::MySql, 'SELECT t.id FROM t CROSS JOIN s', '`t` CROSS JOIN `s`', CrossJoin::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT t.id FROM t FULL JOIN s USING (id)', '"public"."t" FULL JOIN "public"."s" USING("id")', UsingJoin::class])]
    #[TestWith([Dialect::PostgreSql, 'SELECT t.id FROM t RIGHT JOIN s ON t.id = s.id', '"public"."t" RIGHT JOIN "public"."s" ON ("t"."id" = "s"."id")', OnJoin::class])]
    #[TestWith([Dialect::Sqlite, 'SELECT t.id FROM t LEFT OUTER JOIN s', '"main"."t" LEFT JOIN "main"."s"', UnconditionalOuterJoin::class])]
    public function testWriteSerializesEachMatchingOperation(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $join = $statement->from;
        self::assertInstanceOf(Join::class, $join);
        self::assertSame($class, $join::class);
        self::assertSame($expected, Joins::write($join, $dialect)->toString());
        self::assertStringEndsWith(' FROM ' . $expected, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWriteParenthesizesANestedLeftOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(id INT)'));
        $statement = $binder->bind('SELECT 1 FROM t JOIN s ON t.id = s.id JOIN t AS u ON u.id = s.id');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $join = $statement->from;
        self::assertInstanceOf(Join::class, $join);
        self::assertInstanceOf(Join::class, $join->left);
        self::assertSame('("public"."t" INNER JOIN "public"."s" ON ("t"."id" = "s"."id")) INNER JOIN "public"."t" AS "u" ON ("u"."id" = "s"."id")', Joins::write($join, Dialect::PostgreSql)->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWriteParenthesizesANestedRightOperand(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(id INT)'));
        $statement = $binder->bind('SELECT 1 FROM t JOIN (s JOIN t AS u ON s.id = u.id) ON t.id = s.id');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $join = $statement->from;
        self::assertInstanceOf(Join::class, $join);
        self::assertInstanceOf(Join::class, $join->right);
        self::assertSame('"public"."t" INNER JOIN("public"."s" INNER JOIN "public"."t" AS "u" ON ("s"."id" = "u"."id")) ON ("t"."id" = "s"."id")', Joins::write($join, Dialect::PostgreSql)->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testWriteOmitsInnerAfterNaturalForEveryMySqlRelease(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT, b INT)', 'CREATE TABLE u(b INT, c INT)'));
        $statement = $binder->bind('UPDATE LOW_PRIORITY ( t NATURAL JOIN u ) SET a = DEFAULT');
        self::assertSame('UPDATE LOW_PRIORITY `t` NATURAL JOIN `u` SET `a` = DEFAULT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
