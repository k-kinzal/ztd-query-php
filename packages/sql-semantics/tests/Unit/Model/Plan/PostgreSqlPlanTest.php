<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\PostgreSqlFormat;
use SqlSemantics\Model\Plan\PostgreSqlPlan;
use SqlSemantics\Model\Plan\SerializationCost;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlPlan::class)]
#[Medium]
final class PostgreSqlPlanTest extends TestCase
{
    public function testRetainsEveryRequestedOption(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('EXPLAIN (ANALYZE, WAL, FORMAT JSON, SERIALIZE BINARY, BUFFERS OFF, TIMING ON) SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        $options = $statement->options;
        self::assertInstanceOf(PostgreSqlPlan::class, $options);
        self::assertTrue($options->analyze);
        self::assertTrue($options->wal);
        self::assertTrue($options->timing);
        self::assertFalse($options->buffers);
        self::assertSame(SerializationCost::Binary, $options->serialization);
        self::assertSame(PostgreSqlFormat::Json, $options->format);
        self::assertSame(Dialect::PostgreSql, $options->dialect());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testDialectIsPostgreSql(): void
    {
        self::assertSame(Dialect::PostgreSql, (new PostgreSqlPlan())->dialect());
        self::assertSame(Dialect::PostgreSql, (new PostgreSqlPlan(analyze: true, wal: true))->dialect());
    }

    public function testDefaultsToACostOnlyTextPlan(): void
    {
        $options = new PostgreSqlPlan();
        self::assertFalse($options->analyze);
        self::assertTrue($options->costs);
        self::assertNull($options->timing);
        self::assertNull($options->summary);
        self::assertSame(SerializationCost::None, $options->serialization);
        self::assertSame(PostgreSqlFormat::Text, $options->format);
        self::assertSame([false, false, false, false, false, false], [$options->verbose, $options->settings, $options->genericPlan, $options->buffers, $options->wal, $options->memory]);
    }

    public function testAcceptsDisabledTimingWithoutAnalyze(): void
    {
        $options = new PostgreSqlPlan(timing: false, genericPlan: true);
        self::assertFalse($options->timing);
        self::assertTrue($options->genericPlan);
    }

    public function testRejectsAGenericPlanTogetherWithAnalyze(): void
    {
        $this->expectException(InvalidStructure::class);
        new PostgreSqlPlan(analyze: true, genericPlan: true);
    }

    public function testRejectsWalMeasurementsWithoutAnalyze(): void
    {
        $this->expectException(InvalidStructure::class);
        new PostgreSqlPlan(wal: true);
    }

    public function testRejectsTimingWithoutAnalyze(): void
    {
        $this->expectException(InvalidStructure::class);
        new PostgreSqlPlan(timing: true);
    }

    public function testRejectsSerializationCostsWithoutAnalyze(): void
    {
        $this->expectException(InvalidStructure::class);
        new PostgreSqlPlan(serialization: SerializationCost::Text);
    }
}
