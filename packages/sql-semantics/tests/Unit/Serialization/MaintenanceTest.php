<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\AttachDatabaseStatement;
use SqlSemantics\Model\Statement\Maintenance\ReindexDatabaseStatement;
use SqlSemantics\Model\Statement\Maintenance\ReindexObjectStatement;
use SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Maintenance;

#[CoversClass(Maintenance::class)]
#[Medium]
final class MaintenanceTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'TRUNCATE TABLE t', 'TRUNCATE TABLE `t`'])]
    #[TestWith([Dialect::PostgreSql, 'TRUNCATE ONLY t, u RESTART IDENTITY CASCADE', 'TRUNCATE TABLE ONLY "public"."t", "public"."u" RESTART IDENTITY CASCADE'])]
    #[TestWith([Dialect::Sqlite, 'REINDEX', 'REINDEX'])]
    #[TestWith([Dialect::Sqlite, 'REINDEX main.t', 'REINDEX "main"."t"'])]
    #[TestWith([Dialect::PostgreSql, 'REINDEX TABLE t', 'REINDEX TABLE "t"'])]
    #[TestWith([Dialect::PostgreSql, 'REINDEX SYSTEM', 'REINDEX SYSTEM'])]
    #[TestWith([Dialect::PostgreSql, 'REINDEX DATABASE app', 'REINDEX DATABASE "app"'])]
    #[TestWith([Dialect::Sqlite, 'ANALYZE', 'ANALYZE'])]
    #[TestWith([Dialect::Sqlite, 'ANALYZE main.t', 'ANALYZE "main"."t"'])]
    #[TestWith([Dialect::Sqlite, 'VACUUM', 'VACUUM'])]
    #[TestWith([Dialect::Sqlite, 'VACUUM main', 'VACUUM "main"'])]
    #[TestWith([Dialect::Sqlite, "VACUUM main INTO '/tmp/x.db'", "VACUUM \"main\" INTO '/tmp/x.db'"])]
    #[TestWith([Dialect::Sqlite, 'DETACH DATABASE other', 'DETACH DATABASE "other"'])]
    #[TestWith([Dialect::MySql, 'USE app', 'USE `app`'])]
    public function testWriteSerializesEachMaintenanceOperationFromItsOperands(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
        $rebound = $binder->bind($expected, strict: false);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteKeepsTruncateIdentityAndReferencePolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('TRUNCATE ONLY t, u RESTART IDENTITY CASCADE');
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(TruncateRelationsStatement::class, $rebound);
        self::assertSame($statement->identities, $rebound->identities);
        self::assertSame($statement->references, $rebound->references);
        self::assertSame(['t', 'u'], array_map(static fn ($table): string => $table->declaration->name, $rebound->tables));
    }

    public function testWriteAttachesWithTheExplicitEncryptionKey(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind("ATTACH 'x.db' AS other KEY 'k'", strict: false);
        self::assertInstanceOf(AttachDatabaseStatement::class, $statement);
        self::assertSame("ATTACH DATABASE 'x.db' AS \"other\" KEY 'k'", $statement->toString());
        self::assertSame("ATTACH DATABASE 'x.db' AS \"other\"", $binder->bind("ATTACH 'x.db' AS other", strict: false)->toString());
    }

    public function testReindexWritesObjectAndDatabaseTargets(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)'));
        $object = $binder->bind('REINDEX TABLE t');
        self::assertInstanceOf(ReindexObjectStatement::class, $object);
        self::assertSame('REINDEX TABLE "t"', Maintenance::reindex($object)->toString());
        $database = $binder->bind('REINDEX DATABASE app');
        self::assertInstanceOf(ReindexDatabaseStatement::class, $database);
        self::assertSame('REINDEX DATABASE "app"', Maintenance::reindex($database)->toString());
    }

    #[TestWith(['REINDEX (CONCURRENTLY, VERBOSE, TABLESPACE x) TABLE t', ReindexObjectStatement::class, 'REINDEX(CONCURRENTLY, VERBOSE, TABLESPACE "x") TABLE "t"'])]
    #[TestWith(['REINDEX (VERBOSE) TABLE t', ReindexObjectStatement::class, 'REINDEX(VERBOSE) TABLE "t"'])]
    #[TestWith(['REINDEX TABLE t', ReindexObjectStatement::class, 'REINDEX TABLE "t"'])]
    #[TestWith(['REINDEX (TABLESPACE x) INDEX i', ReindexObjectStatement::class, 'REINDEX(TABLESPACE "x") INDEX "i"'])]
    public function testWriteSpellsReindexOptions(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }

    #[TestWith(['VACUUM main INTO \'f\'', \SqlSemantics\Model\Statement\Maintenance\VacuumIntoStatement::class, 'VACUUM "main" INTO \'f\''])]
    #[TestWith(['VACUUM INTO \'f\'', \SqlSemantics\Model\Statement\Maintenance\VacuumIntoStatement::class, 'VACUUM INTO \'f\''])]
    #[TestWith(['VACUUM', \SqlSemantics\Model\Statement\Maintenance\VacuumDatabaseStatement::class, 'VACUUM'])]
    public function testWriteSpellsSqliteVacuumTargets(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
