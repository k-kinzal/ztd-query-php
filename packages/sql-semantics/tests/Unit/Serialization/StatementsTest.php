<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerWriteSpellsEachMaintenanceStatement(): array
    {
        return [
            [Dialect::MySql, null, 'TRUNCATE TABLE t', [\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, 'TRUNCATE TABLE `t`']],
            [Dialect::PostgreSql, null, 'TRUNCATE t, u', [\SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, 'TRUNCATE TABLE "public"."t", "public"."u" CONTINUE IDENTITY RESTRICT']],
            [Dialect::PostgreSql, null, 'REINDEX TABLE t', [\SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement::class, 'REINDEX TABLE "t"']],
            [Dialect::PostgreSql, null, 'REINDEX DATABASE d', [\SqlSemantics\Model\Statement\Maintenance\ReindexDatabaseStatement::class, 'REINDEX DATABASE "d"']],
            [Dialect::Sqlite, null, 'REINDEX', [\SqlSemantics\Model\Statement\Maintenance\ReindexAllStatement::class, 'REINDEX']],
            [Dialect::Sqlite, null, 'REINDEX t', [\SqlSemantics\Model\Statement\Maintenance\ReindexNamedStatement::class, 'REINDEX "t"']],
            [Dialect::Sqlite, null, 'ANALYZE', [\SqlSemantics\Model\Statement\Maintenance\AnalyzeAllStatement::class, 'ANALYZE']],
            [Dialect::Sqlite, null, 'ANALYZE t', [\SqlSemantics\Model\Statement\Maintenance\AnalyzeNamedStatement::class, 'ANALYZE "t"']],
            [Dialect::Sqlite, null, 'VACUUM', [\SqlSemantics\Model\Statement\Maintenance\VacuumDatabaseStatement::class, 'VACUUM']],
            [Dialect::Sqlite, null, 'VACUUM INTO \'f\'', [\SqlSemantics\Model\Statement\Maintenance\VacuumIntoStatement::class, 'VACUUM INTO \'f\'']],
            [Dialect::Sqlite, null, 'ATTACH \'f\' AS d', [\SqlSemantics\Model\Statement\Maintenance\AttachDatabaseStatement::class, 'ATTACH DATABASE \'f\' AS "d"']],
            [Dialect::Sqlite, null, 'DETACH d', [\SqlSemantics\Model\Statement\Maintenance\DetachDatabaseStatement::class, 'DETACH DATABASE "d"']],
            [Dialect::MySql, null, 'USE d', [\SqlSemantics\Model\Statement\Maintenance\UseDatabaseStatement::class, 'USE `d`']],
        ];
    }

    #[DataProvider('providerWriteSpellsEachMaintenanceStatement')]
    public function testWriteSpellsEachMaintenanceStatement(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, Statements::write($statement)->toString()]);
    }
}
