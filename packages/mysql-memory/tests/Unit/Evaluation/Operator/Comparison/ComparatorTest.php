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
        self::assertSame(['5', '1.5', 'x'], [Comparator::text(5, Domain::integer()), Comparator::text(1.5, Domain::double()), Comparator::text('x', Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')))]);
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
}
