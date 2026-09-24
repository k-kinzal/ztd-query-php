<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Insert\InsertDefaultValuesStatement;
use SqlSemantics\Model\Statement\Insert\InsertSelectStatement;
use SqlSemantics\Model\Statement\Insert\InsertSetStatement;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Insertions;

#[CoversClass(Insertions::class)]
#[Medium]
final class InsertionsTest extends TestCase
{
    /**
     * @param class-string<InsertStatement> $class
     */
    #[TestWith([Dialect::MySql, 'INSERT LOW_PRIORITY IGNORE INTO t (id, n) VALUES (1, 2), (3, DEFAULT)', 'INSERT LOW_PRIORITY IGNORE INTO `t`(`id`, `n`) VALUES (1, 2), (3, DEFAULT)', InsertValuesStatement::class])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t SET id = 1, n = 2', 'INSERT INTO `t` SET `id` = 1, `n` = 2', InsertSetStatement::class])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t (id) VALUES (1) ON DUPLICATE KEY UPDATE n = 2', 'INSERT INTO `t`(`id`) VALUES (1) ON DUPLICATE KEY UPDATE `n` = 2', InsertValuesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t (id) OVERRIDING SYSTEM VALUE VALUES (1) ON CONFLICT (id) WHERE id > 0 DO UPDATE SET n = excluded.n WHERE t.n < 1 RETURNING id', 'INSERT INTO "public"."t"("id") OVERRIDING SYSTEM VALUE VALUES (1) ON CONFLICT("id") WHERE ("id" > 0) DO UPDATE SET "n" = "excluded"."n" WHERE ("t"."n" < 1) RETURNING "id" AS "id"', InsertValuesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t DEFAULT VALUES', 'INSERT INTO "public"."t" DEFAULT VALUES', InsertDefaultValuesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'WITH c AS (SELECT 1 AS x) INSERT INTO t (id) SELECT x FROM c ON CONFLICT ON CONSTRAINT t_key DO NOTHING', 'WITH "c" AS (SELECT 1 AS "x") INSERT INTO "public"."t"("id") SELECT "x" AS "x" FROM "c" ON CONFLICT ON CONSTRAINT "t_key" DO NOTHING', InsertSelectStatement::class])]
    #[TestWith([Dialect::Sqlite, 'INSERT OR REPLACE INTO t (id) VALUES (1) ON CONFLICT DO NOTHING', 'INSERT OR REPLACE INTO "main"."t"("id") VALUES (1) ON CONFLICT DO NOTHING', InsertValuesStatement::class])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t DEFAULT VALUES', 'INSERT INTO "main"."t" DEFAULT VALUES', InsertDefaultValuesStatement::class])]
    public function testWriteSerializesEachInsertionFormFromItsOperands(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, Insertions::write($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf($class, $rebound);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteInsideATriggerNamesTheBareTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)')))->bind('INSERT INTO t (id) VALUES (1)');
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertSame('INSERT INTO "t"("id") VALUES (1)', Insertions::write($statement, true)->toString());
        self::assertSame('INSERT INTO "main"."t"("id") VALUES (1)', Insertions::write($statement)->toString());
    }

    public function testWriteSpellsTheMysqlEmptyRowAsAnEmptyValuesList(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('INSERT INTO t () VALUES ()');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertSame('INSERT INTO `t` VALUES ()', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testHeaderWritesModeSchedulingAndViolationPolicies(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('INSERT LOW_PRIORITY IGNORE INTO t (id) VALUES (1)');
        self::assertInstanceOf(InsertStatement::class, $mysql);
        self::assertSame('INSERT LOW_PRIORITY IGNORE INTO', Insertions::header($mysql)->toString());
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)')))->bind('INSERT OR REPLACE INTO t (id) VALUES (1)');
        self::assertInstanceOf(InsertStatement::class, $sqlite);
        self::assertSame('INSERT OR REPLACE INTO', Insertions::header($sqlite)->toString());
    }

    public function testOverridingWritesOnlyAnExplicitPostgresIdentityOverride(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $explicit = $binder->bind('INSERT INTO t (id) OVERRIDING USER VALUE VALUES (1)');
        self::assertInstanceOf(InsertStatement::class, $explicit);
        self::assertSame('OVERRIDING USER VALUE', Insertions::overriding($explicit)->toString());
        $implicit = $binder->bind('INSERT INTO t (id) VALUES (1)');
        self::assertInstanceOf(InsertStatement::class, $implicit);
        self::assertSame('', Insertions::overriding($implicit)->toString());
    }
}
