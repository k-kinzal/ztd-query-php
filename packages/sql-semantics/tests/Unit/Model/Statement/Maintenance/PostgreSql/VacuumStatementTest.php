<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\MaintenanceTarget;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumOptions;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(VacuumStatement::class)]
#[Medium]
final class VacuumStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('VACUUM FULL t');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertTrue($copy->options->full);
        self::assertCount(1, $copy->targets);
        self::assertSame(StatementKind::Vacuum, $copy->kind);
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('VACUUM t');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        self::assertSame('VACUUM(FREEZE, TRUNCATE FALSE) "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions(new VacuumOptions(freeze: true, truncate: false))));
        self::assertSame('VACUUM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithTargetsReplacesTheRelations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('VACUUM ANALYZE t');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        self::assertSame('VACUUM(ANALYZE)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTargets([])));
        self::assertSame('VACUUM(ANALYZE) "public"."t"("a")', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTargets([new MaintenanceTarget($statement->targets[0]->table, ['a'])])));
    }

    public function testRejectsAColumnListWithoutAnalyze(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('VACUUM t');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTargets([new MaintenanceTarget($statement->targets[0]->table, ['a'])]);
    }

    public function testRejectsDatabaseStatisticsWithTables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('VACUUM t');
        self::assertInstanceOf(VacuumStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new VacuumOptions(onlyDatabaseStats: true));
    }
}
