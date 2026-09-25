<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\DateShift;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DateShift::class)]
#[Medium]
final class DateShiftTest extends TestCase
{
    public function testInputsPreserveParameterBindingOrderForALeadingInterval(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT INTERVAL ? DAY + ?');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        self::assertSame([$shift->quantity, $shift->value], $shift->inputs());
        self::assertSame('bigint', $shift->quantity->type->name);
        self::assertSame('date', $shift->value->type->name);
        self::assertSame('SELECT (INTERVAL ? DAY + ?)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testSpellingRetainsTheSubtractionOperation(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE - INTERVAL 2 DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        self::assertSame('DATE_SUB', $shift->spelling());
        self::assertSame([$shift->value, $shift->quantity], $shift->inputs());
    }

    public function testWithFactsKeepsDerivedOperandFacts(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        $copy = $shift->withFacts($shift->facts);
        self::assertNotSame($shift, $copy);
        self::assertSame($shift->value, $copy->value);
        self::assertSame($shift->unit, $copy->unit);
    }

    public function testWithFactsRejectsContradictoryNullability(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $shift->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($shift->type, \SqlSemantics\Type\Nullability::NotNull));
    }

    public function testRejectsSubtractionWithALeadingInterval(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new DateShift($shift->source, $shift->value, $shift->quantity, $shift->unit, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Subtract, $shift->rules, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::IntervalFirst);
    }

    public function testRejectsOperandsFromAnotherDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $input = $query->outputs[0]->expression;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new DateShift($query->source, $input, $input, \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Add, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current);
    }

    public function testQuantityCanBeReplacedWithoutChangingTheOriginalStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT INTERVAL 2 DAY + CURRENT_DATE');
        $replacement = $binder->bind('SELECT 3');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(BoundSelect::class, $replacement);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(DateShift::class, $shift);
        $changed = $query->replaceExpression($shift->quantity, $replacement->outputs[0]->expression);
        self::assertStringContainsString('INTERVAL 3 DAY', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertStringContainsString('INTERVAL 2 DAY', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }
}
