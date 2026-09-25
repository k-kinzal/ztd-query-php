<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteUsingStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateFromStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Mutations;

#[CoversClass(Mutations::class)]
#[Medium]
final class MutationsTest extends TestCase
{
    /**
     * @param class-string<DeleteStatement|UpdateStatement> $class
     */
    #[TestWith([Dialect::MySql, 'UPDATE LOW_PRIORITY IGNORE t SET n = 1 WHERE id = 1 ORDER BY id LIMIT 2', 'UPDATE LOW_PRIORITY IGNORE `t` SET `n` = 1 WHERE (`id` = 1) ORDER BY `id` ASC LIMIT 2', UpdateTableStatement::class])]
    #[TestWith([Dialect::MySql, 'UPDATE t JOIN s ON t.id = s.id SET t.n = s.n', 'UPDATE `t` INNER JOIN `s` ON (`t`.`id` = `s`.`id`) SET `t`.`n` = `s`.`n`', UpdateJoinedStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'WITH c AS (SELECT 1) UPDATE t SET n = s.n FROM s WHERE t.id = s.id RETURNING t.id', 'WITH "c" AS (SELECT 1) UPDATE "public"."t" SET "n" = "s"."n" FROM "public"."s" WHERE ("t"."id" = "s"."id") RETURNING "t"."id" AS "id"', UpdateFromStatement::class])]
    #[TestWith([Dialect::MySql, 'DELETE LOW_PRIORITY QUICK IGNORE FROM t WHERE id = 1 ORDER BY id LIMIT 2', 'DELETE LOW_PRIORITY QUICK IGNORE FROM `t` WHERE (`id` = 1) ORDER BY `id` ASC LIMIT 2', DeleteTableStatement::class])]
    #[TestWith([Dialect::MySql, 'DELETE t, s FROM t JOIN s ON t.id = s.id', 'DELETE `t`, `s` FROM `t` INNER JOIN `s` ON (`t`.`id` = `s`.`id`)', DeleteJoinedStatement::class])]
    #[TestWith([Dialect::MySql, 'DELETE a FROM t AS a JOIN s ON a.id = s.id', 'DELETE `a` FROM `t` AS `a` INNER JOIN `s` ON (`a`.`id` = `s`.`id`)', DeleteJoinedStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DELETE FROM t USING s WHERE t.id = s.id RETURNING t.id', 'DELETE FROM "public"."t" USING "public"."s" WHERE ("t"."id" = "s"."id") RETURNING "t"."id" AS "id"', DeleteUsingStatement::class])]
    public function testWriteSerializesEachMutationFormFromItsOperands(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertInstanceOf($class, $rebound);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testUpdateInsideATriggerNamesTheBareTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)')))->bind('UPDATE OR IGNORE t SET n = 1');
        self::assertInstanceOf(UpdateStatement::class, $statement);
        self::assertSame('UPDATE OR IGNORE "t" SET "n" = 1', Mutations::update($statement, true)->toString());
        self::assertSame('UPDATE OR IGNORE "main"."t" SET "n" = 1', Mutations::update($statement)->toString());
    }

    public function testDeleteInsideATriggerNamesTheBareTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)')))->bind('DELETE FROM t WHERE id = 1');
        self::assertInstanceOf(DeleteStatement::class, $statement);
        self::assertSame('DELETE FROM "t"', Mutations::delete($statement, true)->toString());
        self::assertSame('DELETE FROM "main"."t" WHERE ("id" = 1)', Mutations::write($statement)->toString());
    }

    public function testWriteReboundMutationKeepsItsOrderingAndLimit(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('UPDATE t SET n = 1 ORDER BY id LIMIT 2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(UpdateTableStatement::class, $rebound);
        self::assertNotNull($rebound->limit);
        self::assertSame('2', $rebound->limit->spelling());
        self::assertCount(1, $rebound->orderBy);
    }
}
