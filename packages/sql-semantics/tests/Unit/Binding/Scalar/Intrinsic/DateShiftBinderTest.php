<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\DateShiftBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DateShiftBinder::class)]
#[Medium]
final class DateShiftBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\Expression> $expected
     */
    #[DataProvider('providerForms')]
    public function testBindPreservesTheIntervalRoles(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($expected, $query->outputs[0]->expression);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query), strict: false)));
    }

    /**
     * @return iterable<string, array{string, class-string<\SqlSemantics\Model\Expression>}>
     */
    public static function providerForms(): iterable
    {
        yield 'add' => ['SELECT CURRENT_DATE + INTERVAL 2 DAY', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'subtract' => ['SELECT CURRENT_DATE - INTERVAL 2 DAY', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'leading' => ['SELECT INTERVAL 2 DAY + CURRENT_DATE', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'date_add' => ['SELECT DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'date_sub' => ['SELECT DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'adddate days' => ['SELECT ADDDATE(CURRENT_DATE, 2)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'subdate days' => ['SELECT SUBDATE(CURRENT_DATE, 2)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'adddate interval' => ['SELECT ADDDATE(CURRENT_DATE, INTERVAL 2 HOUR)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'subdate interval' => ['SELECT SUBDATE(CURRENT_DATE, INTERVAL 2 HOUR)', \SqlSemantics\Model\Scalar\Temporal\DateShift::class];
        yield 'ordinary quoted function' => ['SELECT `date_add`(1, 2)', \SqlSemantics\Model\Scalar\Function\FunctionCall::class];
    }

    public function testBindDiagnosesRowOperands(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL ROW(1,2) DAY', strict: false);
    }

    public function testBindKeepsCompositeUnitAndOperandOrder(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT INTERVAL '1 02' DAY_HOUR + CURRENT_TIMESTAMP");
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\DateShift::class, $shift);
        self::assertSame(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DayHour, $shift->unit);
        self::assertSame(\SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::IntervalFirst, $shift->operandOrder);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $shift->quantity);
        self::assertSame("'1 02'", $shift->quantity->text);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindReadsTheShiftOperandsUnitAndRules(): array
    {
        return [
            [Dialect::MySql, null, 'select date_add(\'2020-01-01\', interval 1 day)', ['\'2020-01-01\'', '1', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Add, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::TemporalFirst]],
            [Dialect::MySql, null, 'select subdate(\'2020-01-01\', 5)', ['\'2020-01-01\'', '5', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Subtract, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::TemporalFirst]],
            [Dialect::MySql, null, 'select adddate(\'2020-01-01\', 5)', ['\'2020-01-01\'', '5', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Add, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::TemporalFirst]],
            [Dialect::MySql, null, 'select \'2020-01-01\' - interval 3 hour', ['\'2020-01-01\'', '3', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Hour, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Subtract, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::TemporalFirst]],
            [Dialect::MySql, null, 'select interval 4 minute + \'2020-01-01\'', ['\'2020-01-01\'', '4', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Minute, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Add, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::IntervalFirst]],
            [Dialect::MySql, 'mysql-5.7.44', 'select date_sub(\'2020-01-01\', interval 1 day)', ['\'2020-01-01\'', '1', \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, \SqlSemantics\Model\Scalar\Temporal\ShiftDirection::Subtract, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Legacy, \SqlSemantics\Model\Scalar\Temporal\IntervalOperandOrder::TemporalFirst]],
        ];
    }

    #[DataProvider('providerBindReadsTheShiftOperandsUnitAndRules')]
    public function testBindReadsTheShiftOperandsUnitAndRules(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $shift = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\DateShift::class, $shift);
        self::assertSame($expected, [$shift->value->spelling(), $shift->quantity->spelling(), $shift->unit, $shift->direction, $shift->rules, $shift->operandOrder]);
    }
}
