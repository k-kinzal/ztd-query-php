<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Time\Readings;
use MySqlMemory\Evaluation\Function\Time\Zeros;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Readings::class)]
#[Small]
final class ReadingsTest extends TestCase
{
    public function testMomentReadsAnArgument(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame([2024, 1, 5, 10, 0, 0, 500000], (new Readings())->moment($frame, new Constant(Domain::string(22, Collation::known('utf8mb4_0900_ai_ci')), '2024-01-05 10:00:00.5'), Zeros::Modes));
    }

    public function testValueRefusesZeroPartsAsTheFunctionDoes(): void
    {
        $instance = new Instance();
        $diagnostics = new Diagnostics();
        $context = new Context(new SqlModes(['NO_ZERO_IN_DATE']), $diagnostics, new Variables($instance->catalog, $instance->globals), 0.0);
        $text = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([[2024, 1, 0, 0, 0, 0, 0], null, null, [2024, 1, 0, 0, 0, 0, 0]], [(new Readings())->value('2024-01-00', $text, Zeros::Dated, $context), (new Readings())->value('2024-01-00', $text, Zeros::Refused, $context), (new Readings())->value('2024-01-00', $text, Zeros::Modes, $context), (new Readings())->value('2024-01-00', $text, Zeros::Months, $context)]);
        self::assertSame(2, $diagnostics->count());
    }

    public function testRefuseWarnsOfAnIncorrectDatetime(): void
    {
        $instance = new Instance();
        $diagnostics = new Diagnostics();
        (new Readings())->refuse('x', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), new Context(new SqlModes([]), $diagnostics, new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(1, $diagnostics->count());
    }

    public function testTimeReadsATime(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame([true, 1, 2, 3, 0], (new Readings())->time($frame, new Constant(Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')), '-1:02:03')));
    }

    public function testIntegerReadsAnInteger(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(2, (new Readings())->integer($frame, new Constant(Domain::decimal(2, 1), '1.5')));
    }

    public function testDayNumberCountsZeroMonthsAndDays(): void
    {
        self::assertSame([739251, 1, 0, 739219], [Readings::dayNumber(2024, 1, 1), Readings::dayNumber(0, 1, 1), Readings::dayNumber(0, 0, 0), Readings::dayNumber(2024, 0, 0)]);
    }

    public function testWeekdayStartsOnMonday(): void
    {
        self::assertSame([0, 5], [Readings::weekday(739251), Readings::weekday(0)]);
    }
}
