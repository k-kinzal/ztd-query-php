<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Function\Time\Clocks;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Clocks::class)]
#[Small]
final class ClocksTest extends TestCase
{
    public function testRoutinesNamesTheFunctionsOfTimes(): void
    {
        self::assertSame(['TIME', 'TIMESTAMP', 'ADDTIME', 'SUBTIME', 'TIMEDIFF', 'MAKETIME', 'SEC_TO_TIME', 'TIME_TO_SEC'], array_map(static fn ($routine): string => $routine->name, (new Clocks())->routines()));
    }

    public function testTimeKeepsTheFractionalDigitsALiteralWrites(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TIME('12:00:00.5'), TIME('2024-01-01 10:00:00.12'), TIME('x')")[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['12:00:00.5', '10:00:00.12', null]], $result->rows);
        self::assertSame([1, 2, 6], [$result->columns[0]->decimals, $result->columns[1]->decimals, $result->columns[2]->decimals]);
    }

    public function testTimestampAddsATimeToADate(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TIMESTAMP('2024-01-01', '-1:00:00'), TIMESTAMP('9999-12-31 23:00:00', '2:00:00'), TIMESTAMP('2024-01-01', TIMESTAMP'2024-01-01 10:00:00'), TIMESTAMP('2024-01-01', '2024-01-01 10:00:00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2023-12-31 23:00:00', null, '2024-01-01 10:00:00', null]], $reply->rows);
    }

    public function testAddMovesADatetimeOrATimeAndRefusesADatetimeAmount(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT ADDTIME('2024-01-01 10:00', '1:00'), ADDTIME(TIME'838:00:00', '1:00:00'), ADDTIME('2024-01-01', '1'), SUBTIME('2024-01-01 00:00:00', '1'), ADDTIME('10:00:00', '2024-01-01 00:00:00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-01-01 11:00:00', '838:59:59', '00:20:25', '2023-12-31 23:59:59', null]], $reply->rows);
        $reply = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame(['Warning', '1292', "Truncated incorrect time value: '839:00:00'"], $reply->rows[0]);
    }

    public function testDifferenceNeedsValuesOfOneKind(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TIMEDIFF('10:00', '-10:00'), TIMEDIFF('2024-01-01 00:00:00', '10:00:00'), TIMEDIFF('2024-01-01', '2023-12-31'), TIMEDIFF(TIME'-838:00:00', TIME'838:00:00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['20:00:00', null, '00:00:01', '-838:59:59']], $reply->rows);
    }

    public function testInstantReadsTheKindAndTheMicroseconds(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);
        $text = Domain::string(19, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([['datetime', 86400000000], ['time', 3600000000]], [(new Clocks())->instant('1970-01-02 00:00:00', $text, false, $context), (new Clocks())->instant('01:00:00', $text, false, $context)]);
    }

    public function testDatedTellsAStringWithATimeAfterADate(): void
    {
        $text = Domain::string(19, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([true, false, true], [(new Clocks())->dated('2024-01-01 10:00', $text, true), (new Clocks())->dated('2024-01-01', $text, true), (new Clocks())->dated(20240101101010, Domain::integer(), true)]);
    }

    public function testDurationReadsMicroseconds(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame(-1500000, (new Clocks())->duration('-00:00:01.5', Domain::string(11, Collation::known('utf8mb4_0900_ai_ci')), $context));
    }

    public function testMovedWarnsPastTheLastDate(): void
    {
        $instance = new Instance();
        $diagnostics = new Diagnostics();
        $context = new Context(new SqlModes([]), $diagnostics, new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertNull((new Clocks())->moved([9999, 12, 31, 23, 0, 0, 0], 7200000000, new Domain(Kind::DateTime, Field::DateTime, 19), $context));
        self::assertSame(1, $diagnostics->count());
    }

    public function testClampedStopsAt838Hours(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame('838:59:59.000', (new Clocks())->clamped(5145255123000, new Domain(Kind::Time, Field::Time, 14, 3), $context));
    }

    public function testWrittenDoesNotBoundTheHours(): void
    {
        self::assertSame('-1429:14:15.1', (new Clocks())->written(-5145255100000, 1));
    }

    public function testMakeRefusesMinutesAndSecondsOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT MAKETIME(1, 60, 0), MAKETIME(1, 59, 59.9999999), MAKETIME(-1, 2, 3), MAKETIME(839, 0, 0)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[null, '02:00:00.000000', '-01:02:03', '838:59:59']], $reply->rows);
    }

    public function testFromSecondsClampsAndKeepsANegativeZero(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('SELECT SEC_TO_TIME(3020400), SEC_TO_TIME(-1.5), SEC_TO_TIME(-0.0000001), SEC_TO_TIME(59.9999999)')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['838:59:59', '-00:00:01.5', '-00:00:00.000000', '00:01:00.000000']], $reply->rows);
    }

    public function testToSecondsDropsTheFraction(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TIME_TO_SEC('-838:59:59'), TIME_TO_SEC('2024-01-01 10:00:00'), TIME_TO_SEC(TIME'-00:00:01.5')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[-3020399, 36000, -1]], $reply->rows);
    }
}
