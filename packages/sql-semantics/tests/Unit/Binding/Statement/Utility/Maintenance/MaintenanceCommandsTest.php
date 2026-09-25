<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Maintenance\MaintenanceCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MaintenanceCommands::class)]
#[Medium]
final class MaintenanceCommandsTest extends TestCase
{
    #[TestWith(['VACUUM', 'VACUUM'])]
    #[TestWith(['VACUUM FULL FREEZE VERBOSE ANALYZE t (a, b), s.u', 'VACUUM(FULL, FREEZE, VERBOSE, ANALYZE) "public"."t"("a", "b"), "s"."u"'])]
    #[TestWith(['VACUUM (VERBOSE false, VERBOSE) t', 'VACUUM(VERBOSE) "public"."t"'])]
    public function testVacuumReadsOptionsAndTargets(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf(Statement\VacuumStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
    }

    #[TestWith(['ANALYZE', 'ANALYZE'])]
    #[TestWith(['ANALYSE VERBOSE t', 'ANALYZE(VERBOSE) "public"."t"'])]
    #[TestWith(["ANALYZE (SKIP_LOCKED on, BUFFER_USAGE_LIMIT '1MB') t (a)", 'ANALYZE(SKIP_LOCKED, BUFFER_USAGE_LIMIT \'1MB\') "public"."t"("a")'])]
    public function testAnalyzeReadsOptionsAndTargets(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(Statement\AnalyzeStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['CLUSTER', Statement\ClusterAllStatement::class, 'CLUSTER'])]
    #[TestWith(['CLUSTER (VERBOSE 1)', Statement\ClusterAllStatement::class, 'CLUSTER(VERBOSE)'])]
    #[TestWith(['CLUSTER t USING i', Statement\ClusterTableStatement::class, 'CLUSTER "public"."t" USING "i"'])]
    #[TestWith(['CLUSTER VERBOSE i ON t', Statement\ClusterTableStatement::class, 'CLUSTER(VERBOSE) "public"."t" USING "i"'])]
    public function testClusterSeparatesAllTablesFromOneTable(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testTargetsKeepColumnLists(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ANALYZE t (b), t');
        self::assertInstanceOf(Statement\AnalyzeStatement::class, $statement);
        self::assertSame([['b'], []], array_map(static fn (Statement\MaintenanceTarget $target): array => $target->columns, $statement->targets));
    }

    public function testTableRejectsAnImproperName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RelationName->message());
        $binder->bind('CLUSTER a.b.c.d');
    }

    #[TestWith(['VACUUM t (a)'])]
    #[TestWith(['VACUUM (FULL, PARALLEL 2)'])]
    #[TestWith(['VACUUM (ONLY_DATABASE_STATS) t'])]
    #[TestWith(['VACUUM (FORMAT)'])]
    #[TestWith(["VACUUM (PARALLEL '2')"])]
    #[TestWith(['VACUUM (PARALLEL)'])]
    #[TestWith(['ANALYZE (FULL)'])]
    #[TestWith(["ANALYZE (BUFFER_USAGE_LIMIT '64kB')"])]
    #[TestWith(['ANALYZE (BUFFER_USAGE_LIMIT)'])]
    #[TestWith(["ANALYZE (VERBOSE 'yes')"])]
    #[TestWith(['CLUSTER (ANALYZE)'])]
    public function testBindRejectsRequestsTheServerRejects(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::MaintenanceOption->message());
        $binder->bind($sql);
    }
}
