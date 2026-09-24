<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlPlan::class)]
#[Medium]
final class MySqlPlanTest extends TestCase
{
    public function testRetainsTheAnalyzeRequestWithItsTreeFormat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('EXPLAIN ANALYZE FORMAT=TREE SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        $options = $statement->options;
        self::assertInstanceOf(MySqlPlan::class, $options);
        self::assertTrue($options->analyze);
        self::assertSame(MySqlFormat::Tree, $options->format);
        self::assertFalse($options->extended);
        self::assertFalse($options->partitions);
        self::assertSame(Dialect::MySql, $options->dialect());
        self::assertSame('EXPLAIN ANALYZE FORMAT = TREE SELECT 1', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testDialectIsMySql(): void
    {
        self::assertSame(Dialect::MySql, (new MySqlPlan())->dialect());
        self::assertSame(Dialect::MySql, (new MySqlPlan(MySqlFormat::Json))->dialect());
    }

    public function testDefaultsToAPlainPlanRequest(): void
    {
        $options = new MySqlPlan();
        self::assertSame(MySqlFormat::Default, $options->format);
        self::assertFalse($options->analyze);
        self::assertFalse($options->extended);
        self::assertFalse($options->partitions);
    }

    #[TestWith([MySqlFormat::Default])]
    #[TestWith([MySqlFormat::Tree])]
    public function testAcceptsAnalyzeWithTheTreeFormatOrItsDefault(MySqlFormat $format): void
    {
        $options = new MySqlPlan($format, true, true, true);
        self::assertTrue($options->analyze);
        self::assertTrue($options->extended);
        self::assertTrue($options->partitions);
    }

    #[TestWith([MySqlFormat::Json])]
    #[TestWith([MySqlFormat::Traditional])]
    public function testRejectsAnalyzeWithAFormatThatCannotReportMeasurements(MySqlFormat $format): void
    {
        $this->expectException(InvalidStructure::class);
        new MySqlPlan($format, true);
    }

    public function testKeepsTheVariableReceivingAJsonPlan(): void
    {
        $plan = new MySqlPlan(MySqlFormat::Json, variable: 'plan');
        self::assertSame('plan', $plan->variable);
    }

    #[TestWith([MySqlFormat::Tree])]
    #[TestWith([MySqlFormat::Default])]
    public function testRejectsAVariableWithoutTheJsonFormat(MySqlFormat $format): void
    {
        $this->expectException(InvalidStructure::class);
        new MySqlPlan($format, variable: 'plan');
    }
}
