<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Comparator::class)]
#[Small]
final class ComparatorTest extends TestCase
{
    public function testOfComparesTwoStringsAsStringsInTheirCollation(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $comparator = Comparator::of(Domain::string(10, $collation), Domain::string(10, $collation), '=', $collation);

        self::assertSame([Kind::String, 'utf8mb4_0900_ai_ci'], [$comparator->mode, $comparator->collation->name]);
    }

    public function testOfComparesIntegersAsIntegersAndWithNullAsIntegers(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(
            [Kind::Integer, Kind::Integer, Kind::String],
            [
                Comparator::of(Domain::integer(), Domain::integer(Field::Long, 11, true), '=', $collation)->mode,
                Comparator::of(Domain::integer(), Domain::null(), '=', $collation)->mode,
                Comparator::of(Domain::null(), Domain::null(), '=', $collation)->mode,
            ],
        );
    }

    public function testOfComparesADecimalAndAnIntegerAsDecimals(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $comparator = Comparator::of(Domain::integer(), Domain::decimal(5, 2), '<', $collation);

        self::assertSame([Kind::Decimal, 'binary'], [$comparator->mode, $comparator->collation->name]);
    }

    public function testOfComparesAStringAndANumberAsDoubles(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(
            [Kind::Double, Kind::Double],
            [
                Comparator::of(Domain::integer(), Domain::string(10, $collation), '=', $collation)->mode,
                Comparator::of(Domain::double(), Domain::integer(), '=', $collation)->mode,
            ],
        );
    }

    public function testOfComparesAStringAndADateAsDatetimes(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $comparator = Comparator::of(new Domain(Kind::Date, Field::Date, 10), Domain::string(10, $collation), '=', $collation);

        self::assertSame([Kind::DateTime, 'utf8mb4_0900_ai_ci'], [$comparator->mode, $comparator->collation->name]);
    }

    public function testOfRejectsTwoExplicitCollationsThatDoNotMix(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Illegal mix of collations (utf8mb4_bin,EXPLICIT) and (utf8mb4_0900_ai_ci,EXPLICIT) for operation '='");

        Comparator::of(
            new Domain(Kind::String, Field::VarString, 10, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), true, [], Coercibility::Explicit),
            new Domain(Kind::String, Field::VarString, 10, Domain::NOT_FIXED, false, Collation::known('utf8mb4_0900_ai_ci'), true, [], Coercibility::Explicit),
            '=',
            Collation::known('utf8mb4_0900_ai_ci'),
        );
    }

    public function testModeAnswersTheKindTwoDomainsCompareAs(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame(
            [Kind::String, Kind::String, Kind::Json, Kind::Integer, Kind::DateTime, Kind::Decimal, Kind::Double],
            [
                Comparator::mode(Domain::null(), Domain::null()),
                Comparator::mode(Domain::string(1, $collation), Domain::null()),
                Comparator::mode(new Domain(Kind::Json, Field::Json, 4294967295), Domain::integer()),
                Comparator::mode(new Domain(Kind::Year, Field::Year, 4), Domain::integer()),
                Comparator::mode(Domain::string(10, $collation), new Domain(Kind::Date, Field::Date, 10)),
                Comparator::mode(Domain::decimal(5, 2), Domain::integer()),
                Comparator::mode(Domain::double(), Domain::integer()),
            ],
        );
    }

    public function testTextualHoldsForAStringAndNull(): void
    {
        self::assertSame([true, true, false], [Comparator::textual(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))), Comparator::textual(Domain::null()), Comparator::textual(Domain::integer())]);
    }

    public function testIntegralHoldsForAnIntegerAYearAndABitValue(): void
    {
        self::assertSame([true, true, true, false], [Comparator::integral(Domain::integer()), Comparator::integral(new Domain(Kind::Year, Field::Year, 4)), Comparator::integral(new Domain(Kind::Bit, Field::Bit, 8)), Comparator::integral(Domain::decimal(5, 2))]);
    }

    public function testIntegersHoldsForTwoIntegersAndForAnIntegerAndNull(): void
    {
        self::assertSame([true, true, true, false], [Comparator::integers(Domain::integer(), Domain::integer()), Comparator::integers(Domain::integer(), Domain::null()), Comparator::integers(Domain::null(), Domain::integer()), Comparator::integers(Domain::null(), Domain::null())]);
    }

    public function testTemporalComparesTimesWithTimesAndStringsAsTimes(): void
    {
        $time = new Domain(Kind::Time, Field::Time, 10);
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([Kind::Time, Kind::Time, Kind::Time], [Comparator::temporal($time, $time), Comparator::temporal($time, $string), Comparator::temporal($string, $time)]);
    }

    public function testTemporalComparesATimeWithADateAsDatetimes(): void
    {
        $time = new Domain(Kind::Time, Field::Time, 10);
        $date = new Domain(Kind::Date, Field::Date, 10);
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([Kind::DateTime, Kind::DateTime, Kind::DateTime], [Comparator::temporal($time, $date), Comparator::temporal($date, $time), Comparator::temporal($date, $string)]);
    }

    public function testMomentWritesADatetimeOrATimeWithMicroseconds(): void
    {
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['2024-01-02 00:00:00.000000', '10:00:00.000000'], [Comparator::moment('2024-1-2', $string, Kind::DateTime), Comparator::moment('10:00', $string, Kind::Time)]);
    }

    public function testMomentAnswersNullForATimeOrTextThatIsNoDatetime(): void
    {
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([null, null], [Comparator::moment('10:00:00', new Domain(Kind::Time, Field::Time, 10), Kind::DateTime), Comparator::moment('nope', $string, Kind::DateTime)]);
    }

    public function testTextWritesNumbersAsTheirText(): void
    {
        self::assertSame(['5', '1.5', 'x'], [Comparator::text(5, Domain::integer(), Collation::known('utf8mb4_0900_ai_ci')), Comparator::text(1.5, Domain::double(), Collation::known('utf8mb4_0900_ai_ci')), Comparator::text('x', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), Collation::known('utf8mb4_0900_ai_ci'))]);
    }

    public function testTextConvertsAStringIntoTheCharacterSetOfTheCollation(): void
    {
        self::assertSame(["\xE9", "\x00\xE9"], [Comparator::text('é', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), Collation::known('latin1_swedish_ci')), Comparator::text("\xE9", Domain::string(1, Collation::known('latin1_swedish_ci')), Collation::known('ucs2_general_ci'))]);
    }

    public function testCompareOrdersIntegersAsUnsigned(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $comparator = new Comparator(Kind::Integer, Domain::integer(Field::LongLong, 20, true), Domain::integer(), Collation::binary());

        self::assertSame([1, null, null], [$comparator->compare(-1, 1, $context), $comparator->compare(null, 1, $context), $comparator->compare(1, null, $context)]);
    }

    public function testCompareIgnoresCaseUnderAnAccentAndCaseInsensitiveCollation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $comparator = Comparator::of(Domain::string(10, $collation), Domain::string(10, $collation), '=', $collation);

        self::assertSame([0, 0, -1, 1], [$comparator->compare('a', 'A', $context), $comparator->compare('é', 'E', $context), $comparator->compare('a', 'b', $context), $comparator->compare('a ', 'a', $context)]);
    }

    public function testCompareOrdersDecimalsByValue(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $comparator = Comparator::of(Domain::decimal(5, 2), Domain::integer(), '=', Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([1, 0, -1], [$comparator->compare('1.50', 1, $context), $comparator->compare('1.00', 1, $context), $comparator->compare('-3.25', -3, $context)]);
    }

    public function testTemporalOrderOrdersTimesBeyondTwentyFourHours(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $time = new Domain(Kind::Time, Field::Time, 10);
        $comparator = Comparator::of($time, $time, '=', Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([1, -1], [$comparator->temporalOrder('100:00:00', '99:00:00', $context), $comparator->temporalOrder('-01:00:00', '00:00:00', $context)]);
    }

    public function testTemporalOrderReadsLooseDatetimeText(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $comparator = Comparator::of(new Domain(Kind::DateTime, Field::DateTime, 19), Domain::string(10, $collation), '=', $collation);

        self::assertSame([0, 1], [$comparator->temporalOrder('2024-03-01 00:00:00', '2024-3-1', $context), $comparator->temporalOrder('2024-03-01 00:00:01', '2024-03-01', $context)]);
    }

    public function testCompareComparesUnsignedAndSignedColumnsByValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (u BIGINT UNSIGNED, i BIGINT)');
        $session->query('INSERT INTO t VALUES (18446744073709551615, -1)');
        $result = $session->query('SELECT u > i, u = i, i < u FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
    }

    public function testCompareComparesTemporalColumnsWithStringsAsDatetimes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATETIME, x DATE, tm TIME)');
        $session->query("INSERT INTO t VALUES ('2024-03-01 12:00:00', '2024-03-01', '12:00:00')");
        $result = $session->query("SELECT d > x, d = '2024-03-01 12:00', x = '20240301', tm = '12:00:00', tm < '9:00:00' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '1', '0']], $result->rows);
    }

    public function testCompareComparesAStringAndANumberAsDoublesWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '10' > '9', '10' > 9, 'abc' = 0")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testCompareReadsABitValueAsAnUnsignedInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (b BIT(8), w BIT(64), u BIGINT UNSIGNED); INSERT INTO t VALUES (b'101', b'1111111111111111111111111111111111111111111111111111111111111111', 18446744073709551615)");
        $result = $session->query('SELECT b = 5, 5 = b, b > 4, b <> 5, b IN (5, 6), b BETWEEN 4 AND 6, w = u, w > 9223372036854775807, w = -1 FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '0', '1', '1', '1', '1', '0']], $result->rows);
    }

    public function testIntegerReadsTheBytesOfABitValue(): void
    {
        $instance = new Instance();
        $context = new Context(new \MySqlMemory\Session\SqlModes([]), new \MySqlMemory\Session\Diagnostics(), new \MySqlMemory\Session\Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame([5, -1, 7], [Comparator::integer("\x05", new Domain(Kind::Bit, Field::Bit, 8, 0, true), $context), Comparator::integer(str_repeat("\xFF", 8), new Domain(Kind::Bit, Field::Bit, 64, 0, true), $context), Comparator::integer(7, Domain::integer(), $context)]);
    }
}
