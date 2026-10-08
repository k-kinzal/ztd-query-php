<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Function\Json\Returning;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Returning::class)]
#[Small]
final class ReturningTest extends TestCase
{
    public function testNameNamesTheTypeOfTheOutOfRangeError(): void
    {
        self::assertSame(['UNSIGNED', 'FLOAT', 'STRING', 'DATETIME'], [(new Returning(Domain::integer(Field::LongLong, 21, true)))->name(), (new Returning(new Domain(Kind::Double, Field::Float, 23, 31)))->name(), (new Returning(Domain::string(3, Collation::binary())))->name(), (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19)))->name()]);
    }

    public function testCoerceRefusesAContainerForAColumnButNotForJsonValue(): void
    {
        $returning = new Returning(Domain::string(10, Collation::known('utf8mb4_0900_bin')));

        self::assertSame([[true, '[1]'], [false, null]], [$returning->coerce(JsonNode::parse('[1]'), true), $returning->coerce(JsonNode::parse('[1]'), false)]);
    }

    public function testTextRefusesALongerStringAndTrimsSpacesWhenStoring(): void
    {
        self::assertSame([[false, null], [true, 'abc'], [false, null], [true, "1\0\0"]], [(new Returning(Domain::string(3, Collation::known('utf8mb4_0900_bin'))))->text('abc '), (new Returning(Domain::string(3, Collation::known('utf8mb4_0900_bin')), true))->text('abc '), (new Returning(Domain::string(3, Collation::known('latin1_swedish_ci'))))->text('日'), (new Returning(Domain::string(3, Collation::binary(), Field::String), true))->text('1')]);
    }

    public function testIntegerConvertsWithinTheRangeOfTheType(): void
    {
        self::assertSame([[true, 2], [true, 2], [false, null], [false, null], [true, 12]], [(new Returning(Domain::integer()))->integer(JsonNode::parse('2.5')), (new Returning(Domain::integer()))->integer(JsonNode::parse('1.5')), (new Returning(Domain::integer(Field::Tiny, 4)))->integer(JsonNode::parse('300')), (new Returning(Domain::integer()))->integer(JsonNode::parse('"1.5"')), (new Returning(Domain::integer()))->integer(JsonNode::parse('" 12"'))]);
    }

    public function testDecimalRoundsWithinThePrecision(): void
    {
        self::assertSame([[true, '123.5'], [false, null], [true, '-1.0']], [(new Returning(Domain::decimal(4, 1)))->decimal(JsonNode::parse('123.456')), (new Returning(Domain::decimal(4, 1)))->decimal(JsonNode::parse('1234.5')), (new Returning(Domain::decimal(4, 1)))->decimal(JsonNode::parse('18446744073709551615'))]);
    }

    public function testDoubleReadsAnEmptyStringAsZero(): void
    {
        self::assertSame([[true, 0.0], [true, 100.0], [false, null], [true, 0.0], [false, null]], [(new Returning(Domain::double()))->double(JsonNode::parse('""')), (new Returning(Domain::double()))->double(JsonNode::parse('"1e2"')), (new Returning(Domain::double()))->double(JsonNode::parse('"12x"')), (new Returning(new Domain(Kind::Double, Field::Float, 23, 31)))->double(JsonNode::parse('1.5e300')), (new Returning(new Domain(Kind::Double, Field::Float, 23, 31), true))->double(JsonNode::parse('1.5e300'))]);
    }

    public function testNumericReadsAWholeNumber(): void
    {
        self::assertSame(['1.5', null, null], [Returning::numeric(' 1.5 '), Returning::numeric('12x'), Returning::numeric('')]);
    }

    public function testMomentReadsDatesAndTimes(): void
    {
        self::assertSame(
            [[true, '2020-01-01'], [false, null], [true, '2020-01-01 00:00:00'], [true, '10:11:12'], [false, null], [true, '00:00:03']],
            [
                (new Returning(new Domain(Kind::Date, Field::Date, 10)))->moment(JsonNode::parse('"2020-01-01 10:11:12"')),
                (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19)))->moment(JsonNode::parse('"2020-01-01"')),
                (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19), true))->moment(JsonNode::parse('"2020-01-01"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->moment(JsonNode::parse('"2020-01-01 10:11:12.123456"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->moment(JsonNode::parse('"3"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10), true))->moment(JsonNode::parse('"3"')),
            ],
        );
    }

    public function testDurationReadsANumberAsATime(): void
    {
        self::assertSame(
            [[true, '00:01:03'], [true, '00:00:02'], [false, null], [false, null]],
            [
                (new Returning(new Domain(Kind::Time, Field::Time, 10), true))->duration(JsonNode::parse('103')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10), true))->duration(JsonNode::parse('" 1.5"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10), true))->duration(JsonNode::parse('"x"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10), true))->duration(JsonNode::parse('8390000')),
            ],
        );
    }

    public function testTimeReadsTheTimeOfADatetimeOrATime(): void
    {
        self::assertSame(
            [[true, '10:11:12'], [true, '-01:02:03'], [true, '10:11:12'], [false, null], [false, null]],
            [
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->time(JsonNode::parse('"2020-01-01 10:11:12"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->time(JsonNode::parse('"-1:2:3"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->time(new JsonNode(JsonKind::Time, '10:11:12')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->time(JsonNode::parse('"3"')),
                (new Returning(new Domain(Kind::Time, Field::Time, 10)))->time(new JsonNode(JsonKind::Date, '2020-01-01')),
            ],
        );
    }

    public function testCalendarReadsDatesAndDatetimes(): void
    {
        self::assertSame(
            [[true, '2020-01-01'], [false, null], [true, '2020-01-01 00:00:00'], [true, '2020-01-01 10:11:12'], [false, null], [false, null]],
            [
                (new Returning(new Domain(Kind::Date, Field::Date, 10)))->calendar(JsonNode::parse('"2020-01-01 10:11:12"')),
                (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19)))->calendar(JsonNode::parse('"2020-01-01"')),
                (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19)))->calendar(new JsonNode(JsonKind::Date, '2020-01-01')),
                (new Returning(new Domain(Kind::DateTime, Field::DateTime, 19)))->calendar(new JsonNode(JsonKind::DateTime, '2020-01-01 10:11:12')),
                (new Returning(new Domain(Kind::Date, Field::Date, 10)))->calendar(JsonNode::parse('"2020-02-30"')),
                (new Returning(new Domain(Kind::Date, Field::Date, 10)))->calendar(new JsonNode(JsonKind::Time, '10:11:12')),
            ],
        );
    }

    public function testYearReadsZeroAndTheYearsOfTheType(): void
    {
        self::assertSame([[true, 0], [true, 2020], [false, null], [true, 2001], [true, 1999]], [(new Returning(new Domain(Kind::Year, Field::Year, 4)))->year(JsonNode::parse('false')), (new Returning(new Domain(Kind::Year, Field::Year, 4)))->year(JsonNode::parse('2020')), (new Returning(new Domain(Kind::Year, Field::Year, 4)))->year(JsonNode::parse('1')), (new Returning(new Domain(Kind::Year, Field::Year, 4), true))->year(JsonNode::parse('true')), (new Returning(new Domain(Kind::Year, Field::Year, 4), true))->year(new JsonNode(JsonKind::Integer, '99'))]);
    }

    public function testWithinChecksTheRangeOfASmallerInteger(): void
    {
        self::assertSame([true, false, true, false], [(new Returning(Domain::integer(Field::Tiny, 4)))->within('-128', 8), (new Returning(Domain::integer(Field::Tiny, 4)))->within('128', 8), (new Returning(Domain::integer(Field::Tiny, 3, true)))->within('255', 8), (new Returning(Domain::integer(Field::Tiny, 3, true)))->within('-1', 8)]);
    }
}
