<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Extremes;
use MySqlMemory\Evaluation\Function\Math\Rank;
use MySqlMemory\Evaluation\Function\Math\Tally;
use MySqlMemory\Evaluation\Leaf\Constant;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Extremes::class)]
#[Small]
final class ExtremesTest extends TestCase
{
    public function testRoutinesNamesGreatestAndLeast(): void
    {
        self::assertSame(['GREATEST', 'LEAST'], array_map(static fn ($routine): string => $routine->name, (new Extremes())->routines()));
    }

    public function testResolveWarnsOfJsonOnceAndCountsRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query("SELECT GREATEST(CAST('[1]' AS JSON), a) FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['[1]'], ['[1]']], $result->rows);
        self::assertSame([Field::LongBlob, 4294967295], [$result->columns[0]->type, $result->columns[0]->length]);
        self::assertSame([['Warning', '1235', "This version of MySQL doesn't yet support 'comparison of JSON in the LEAST and GREATEST operators'"]], $warnings->rows);
    }

    public function testExtremeComparesInTheKindOfTheResult(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT GREATEST(1, 2.5), GREATEST(1, 'a'), GREATEST('10', '9'), LEAST('a', 'A'), GREATEST(NULL, 1), GREATEST(TIME'10:00:00', TIME'09:00:00.5')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2.5', 'a', '9', 'a', null, '10:00:00.0']], $result->rows);
        self::assertSame([[Field::NewDecimal, 4], [Field::VarString, 8], [Field::Time, 48]], [[$result->columns[0]->type, $result->columns[0]->length], [$result->columns[1]->type, $result->columns[1]->length], [$result->columns[5]->type, $result->columns[5]->length]]);
    }

    public function testExtremeComparesWithADateAsDates(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT GREATEST(DATE'2020-01-01', 5), LEAST(DATE'2020-01-01', 0), LEAST(DATE'2020-01-01', 20190101), GREATEST(DATE'2020-01-01', '2020-01-02 10:00:00')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['2020-01-01', '0', '2019-01-01', '2020-01-02']], $result->rows);
        self::assertSame([['Warning', '1292', "Incorrect date value: '5' for column 'DATE'2020-01-01'' at row 1"], ['Warning', '1292', "Incorrect date value: '0' for column 'DATE'2020-01-01'' at row 1"]], $warnings->rows);
    }

    public function testExtremeReadsAsANumberWithoutWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT GREATEST('a', 'b') + 0")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['0']], $result->rows);
        self::assertSame([], $warnings->rows);
    }

    public function testDatingComparesAsDatetimesWithADatetime(): void
    {
        $date = new Constant(Domain::of(new Resolved(Kind::Date, Field::Date, 10), false), '2020-01-01');
        $moment = new Constant(Domain::of(new Resolved(Kind::DateTime, Field::DateTime, 19), false), '2020-01-01 00:00:00');

        self::assertSame([Kind::Date, Kind::DateTime], [(new Extremes())->dating([$date]), (new Extremes())->dating([$date, $moment])]);
    }

    public function testRankReadsAValueInTheKindItComparesIn(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $integer = Domain::of(Resolved::integer(), false);
        $rank = (new Extremes())->rank('7', $integer, Kind::Decimal, Domain::of(Resolved::decimal(2, 0), false), $frame, [], 'greatest(7)');

        self::assertSame(['7', '7'], [$rank->order, $rank->value]);
    }

    public function testMomentReadsAValueThatIsNoDateWithAWarning(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $date = new Constant(Domain::of(new Resolved(Kind::Date, Field::Date, 10), false), '2020-01-01');
        $rank = (new Extremes())->moment('abc', Domain::of(Resolved::string(3, Collation::known('utf8mb4_0900_ai_ci')), false), Kind::Date, $frame, [new Tally($date)], "greatest(DATE'2020-01-01','abc')");
        $valid = (new Extremes())->moment('2020-01-02 10:00:00', Domain::of(Resolved::string(19, Collation::known('utf8mb4_0900_ai_ci')), false), Kind::Date, $frame, [$date], '');

        self::assertTrue($rank->dateless());
        self::assertSame([2020, 1, 2, 0, 0, 0, 0], $valid->parts);
        self::assertCount(1, $frame->context->diagnostics->conditions);
    }

    public function testWarnNamesTheArgumentThatDecidesTheComparison(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $date = new Constant(Domain::of(new Resolved(Kind::Date, Field::Date, 10), false), '2020-01-01');
        (new Extremes())->warn($frame, Kind::Date, '5', [new Constant(Domain::of(Resolved::integer(), false), 5), $date], 'greatest(5,`d`.`t`.`dt`)');

        self::assertSame("Incorrect date value: '5' for column 'dt' at row 1", $frame->context->diagnostics->conditions[0][2] ?? null);
    }

    public function testNamesSplitsTheArgumentsOfACall(): void
    {
        self::assertSame(["DATE'2020-01-01'", "'a,b'", 'f(1,2)'], (new Extremes())->names("greatest(DATE'2020-01-01','a,b',f(1,2))"));
    }

    public function testCompareComparesTwoRanks(): void
    {
        $result = Domain::of(Resolved::integer(), false);

        self::assertSame([1, -1], [(new Extremes())->compare(new Rank(-1, -1, true), new Rank(1, 1), Kind::Integer, $result), (new Extremes())->compare(new Rank('1.5', '1.5'), new Rank('2', '2'), Kind::Decimal, $result)]);
    }

    public function testWrittenWritesTheWinnerInTheTypeOfTheResult(): void
    {
        $winner = new Constant(Domain::of(Resolved::integer(), false), 5);
        $text = Domain::of(Resolved::string(10, Collation::known('utf8mb4_0900_ai_ci')), false);

        self::assertSame(['5.00', '2020-01-02', '5'], [
            (new Extremes())->written(new Rank('5', 5), $winner, Kind::Decimal, Domain::of(Resolved::decimal(3, 2), false), [$winner]),
            (new Extremes())->written(new Rank('', '2020-01-02', false, [2020, 1, 2, 10, 0, 0, 0]), $winner, Kind::Date, $text, [$winner]),
            (new Extremes())->written(new Rank('', '5', false, null), $winner, Kind::Date, $text, [$winner]),
        ]);
    }

    public function testTimeWritesATimeWithFractionalDigits(): void
    {
        self::assertSame('10:00:00.50', (new Extremes())->time('10:00:00.5', 2));
    }

    public function testFractionCountsSixForAString(): void
    {
        $string = new Constant(Domain::of(Resolved::string(3, Collation::known('utf8mb4_0900_ai_ci')), false), 'abc');
        $integer = new Constant(Domain::of(Resolved::integer(), false), 1);

        self::assertSame([6, 0], [(new Extremes())->fraction([$integer, $string]), (new Extremes())->fraction([$integer])]);
    }
}
