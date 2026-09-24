<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Statements;
use SqlSemantics\SimpleSerializer;

#[CoversClass(Statements::class)]
#[Medium]
final class StatementsTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'TRUNCATE TABLE t', 'TRUNCATE TABLE `t`'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t', 'SELECT `id` AS `id` FROM `t`'])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t (id) VALUES (1)', 'INSERT INTO `t`(`id`) VALUES (1)'])]
    #[TestWith([Dialect::MySql, 'UPDATE t SET n = 1', 'UPDATE `t` SET `n` = 1'])]
    #[TestWith([Dialect::MySql, 'DELETE FROM t', 'DELETE FROM `t`'])]
    #[TestWith([Dialect::PostgreSql, 'MERGE INTO t USING s ON t.id = s.id WHEN MATCHED THEN DELETE', 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN MATCHED THEN DELETE'])]
    #[TestWith([Dialect::MySql, 'SET @x = 1', 'SET @`x` = 1'])]
    #[TestWith([Dialect::MySql, 'CREATE TABLE u (id INT)', 'CREATE TABLE `u`(`id` integer)'])]
    #[TestWith([Dialect::MySql, 'KILL CONNECTION 42', 'KILL CONNECTION 42'])]
    #[TestWith([Dialect::Sqlite, 'VACUUM', 'VACUUM'])]
    public function testWriteDispatchesEachStatementFamilyBySemanticForm(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, Statements::write($statement)->toString());
        self::assertSame($expected, (new SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, Statements::write($rebound)->toString());
    }

    public function testWriteDoesNotConsultTheOriginalSourceText(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('SELECT   id   FROM t WHERE id = 1');
        $unrelated = $binder->bind('SELECT 1');
        $copy = $statement->withOrigin(new Origin('s0', $unrelated->source, Dialect::Sqlite));
        self::assertSame('SELECT "id" AS "id" FROM "main"."t" WHERE ("id" = 1)', Statements::write($copy)->toString());
        self::assertSame(Statements::write($statement)->toString(), Statements::write($copy)->toString());
    }
}
