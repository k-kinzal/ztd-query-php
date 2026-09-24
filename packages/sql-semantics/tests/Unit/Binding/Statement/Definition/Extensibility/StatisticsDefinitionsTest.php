<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\StatisticsDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\Statistics as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StatisticsDefinitions::class)]
#[Medium]
final class StatisticsDefinitionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE STATISTICS ON a, b FROM t', Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS ON "a", "b" FROM "public"."t"'])]
    #[TestWith(['CREATE STATISTICS s ON (a), b FROM t AS x', Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS "s" ON "a", "b" FROM "public"."t"'])]
    #[TestWith(['CREATE STATISTICS IF NOT EXISTS s ON (a * b) FROM t', Statement\CreateExpressionStatisticsStatement::class, 'CREATE STATISTICS IF NOT EXISTS "s" ON (("a" * "b")) FROM "public"."t"'])]
    public function testCreateSeparatesTheUnivariateForm(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['CREATE STATISTICS s ON a FROM t'])]
    #[TestWith(['CREATE STATISTICS s (mcv) ON (a + 1) FROM t'])]
    #[TestWith(['CREATE STATISTICS s ON a, a FROM t'])]
    #[TestWith(['CREATE STATISTICS s ON a, b FROM t, t AS u'])]
    #[TestWith(['CREATE STATISTICS s ON a, b FROM (SELECT 1) AS x'])]
    #[TestWith(['CREATE STATISTICS s ON a, (b IN (SELECT 1)) FROM t'])]
    #[TestWith(['CREATE STATISTICS s (histogram) ON a, b FROM t'])]
    public function testCreateRejectsImpossibleDefinitions(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind($sql);
            self::fail('The definition must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::StatisticsDefinition, $error->violation);
        }
    }

    #[TestWith(['ALTER STATISTICS s SET STATISTICS DEFAULT', -1, false])]
    #[TestWith(['ALTER STATISTICS IF EXISTS s SET STATISTICS 20000', 10000, true])]
    #[TestWith(['ALTER STATISTICS if SET STATISTICS 0', 0, false])]
    public function testTargetReadsTheSamplingTarget(string $sql, int $target, bool $ifExists): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\SetStatisticsTargetStatement::class, $statement);
        self::assertSame([$target, $ifExists], [$statement->target, $statement->ifExists]);
    }

    public function testTargetRejectsATargetBelowTheDefaultMarker(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS -2');
    }

    public function testKindsRequestsARepeatedKindOnce(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS s (dependencies, mcv, dependencies) ON a, b FROM t');
        self::assertInstanceOf(Statement\CreateStatisticsStatement::class, $statement);
        self::assertSame([Statement\StatisticsKind::Dependencies, Statement\StatisticsKind::MostCommonValues], $statement->kinds);
    }

    public function testTableResolvesTheDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS s ON a, b FROM ONLY t');
        self::assertInstanceOf(Statement\CreateStatisticsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\OnlyTableReference::class, $statement->table);
        self::assertSame(['a', 'b'], array_map(static fn (\SqlSemantics\Schema\ColumnDefinition $column): string => $column->name, $statement->table->declaration->columns));
    }

    public function testElementResolvesColumnsAgainstTheTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)')))->bind('CREATE STATISTICS s ON a, (b) FROM t');
        self::assertInstanceOf(Statement\CreateStatisticsStatement::class, $statement);
        self::assertSame([true, true], array_map(Statement\StatisticsInvariant::column(...), $statement->elements));
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindWritesEachStatisticsForm(): array
    {
        return [
            [Dialect::PostgreSql, null, 'CREATE STATISTICS a.s.x ON a, b FROM t', [Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS "a"."s"."x" ON "a", "b" FROM "public"."t"']],
            [Dialect::PostgreSql, null, 'CREATE STATISTICS IF NOT EXISTS s ON a, b FROM t', [Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS IF NOT EXISTS "s" ON "a", "b" FROM "public"."t"']],
            [Dialect::PostgreSql, null, 'ALTER STATISTICS a.s.x SET STATISTICS 5', [Statement\SetStatisticsTargetStatement::class, 'ALTER STATISTICS "a"."s"."x" SET STATISTICS 5']],
            [Dialect::PostgreSql, null, 'ALTER STATISTICS s SET STATISTICS - 1', [Statement\SetStatisticsTargetStatement::class, 'ALTER STATISTICS "s" SET STATISTICS -1']],
            [Dialect::PostgreSql, null, 'ALTER STATISTICS s SET STATISTICS default', [Statement\SetStatisticsTargetStatement::class, 'ALTER STATISTICS "s" SET STATISTICS -1']],
            [Dialect::PostgreSql, null, 'ALTER STATISTICS s SET STATISTICS 1_000', [Statement\SetStatisticsTargetStatement::class, 'ALTER STATISTICS "s" SET STATISTICS 1000']],
            [Dialect::PostgreSql, null, 'CREATE STATISTICS s (ndistinct, dependencies) ON a, b FROM t', [Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS "s"("ndistinct", "dependencies") ON "a", "b" FROM "public"."t"']],
            [Dialect::PostgreSql, null, 'CREATE STATISTICS s ON a, b FROM ONLY t', [Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS "s" ON "a", "b" FROM ONLY "public"."t"']],
            [Dialect::PostgreSql, null, 'CREATE STATISTICS s ON lower(c), a FROM t', [Statement\CreateStatisticsStatement::class, 'CREATE STATISTICS "s" ON ("lower"("c")), "a" FROM "public"."t"']],
        ];
    }

    #[DataProvider('providerBindWritesEachStatisticsForm')]
    public function testBindWritesEachStatisticsForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INTEGER, b INTEGER, c TEXT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }

    #[TestWith(['CREATE STATISTICS d.a.s.x ON a, b FROM t'])]
    #[TestWith(['ALTER STATISTICS d.a.s.x SET STATISTICS 5'])]
    #[TestWith(['ALTER STATISTICS s SET STATISTICS 1234567890'])]
    public function testTargetRejectsOverlongNamesAndTargets(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, c TEXT)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
