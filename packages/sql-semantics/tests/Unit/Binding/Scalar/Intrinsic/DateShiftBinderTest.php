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
        self::assertSame($query->toString(), $binder->bind($query->toString(), strict: false)->toString());
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
}
