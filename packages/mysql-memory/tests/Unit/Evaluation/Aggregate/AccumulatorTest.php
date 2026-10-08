<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\Accumulator;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Accumulator::class)]
#[Small]
final class AccumulatorTest extends TestCase
{
    public function testAddCountsTheRowsWhoseArgumentsAreNotNull(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Count, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer()));
        $accumulator->add(new Frame($context, [3]));
        $accumulator->add(new Frame($context, [null]));
        $accumulator->add(new Frame($context, [1]));

        self::assertSame(2, $accumulator->result(new Frame($context)));
    }

    public function testAddCountsEveryRowWithoutArguments(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Count, [], false, Domain::integer()));
        $accumulator->add(new Frame($context, [null]));
        $accumulator->add(new Frame($context, [null]));

        self::assertSame(2, $accumulator->result(new Frame($context)));
    }

    public function testAddFoldsEqualValuesOnceWhenDistinct(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::integer(), 0)], true, Domain::decimal(65, 0)));
        $accumulator->add(new Frame($context, [3]));
        $accumulator->add(new Frame($context, [3]));
        $accumulator->add(new Frame($context, [4]));

        self::assertSame('7', $accumulator->result(new Frame($context)));
    }

    public function testFoldKeepsTheSmallestAndLargestValue(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $minimum = new Accumulator(new Accumulation(AggregateFunction::Minimum, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer()));
        $maximum = new Accumulator(new Accumulation(AggregateFunction::Maximum, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer()));
        $minimum->add(new Frame($context, [5]));
        $minimum->add(new Frame($context, [-2]));
        $minimum->add(new Frame($context, [9]));
        $maximum->add(new Frame($context, [5]));
        $maximum->add(new Frame($context, [-2]));
        $maximum->add(new Frame($context, [9]));

        self::assertSame([-2, 9], [$minimum->result(new Frame($context)), $maximum->result(new Frame($context))]);
    }

    public function testFoldComparesStringsInTheirCollation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Maximum, [new ColumnRead(Domain::string(1, Collation::known('utf8mb4_0900_ai_ci')), 0)], false, Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))));
        $accumulator->add(new Frame($context, ['b']));
        $accumulator->add(new Frame($context, ['B']));
        $accumulator->add(new Frame($context, ['a']));

        self::assertSame('b', $accumulator->result(new Frame($context)));
    }

    public function testFoldAddsDoublesForADoubleSum(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::double(), 0)], false, Domain::double()));
        $accumulator->add(new Frame($context, [1.5]));
        $accumulator->add(new Frame($context, [2.25]));

        self::assertSame(3.75, $accumulator->result(new Frame($context)));
    }

    public function testFoldCombinesTheBitsOfEachValue(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $and = new Accumulator(new Accumulation(AggregateFunction::BitAnd, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer(Field::LongLong, 21, true)));
        $or = new Accumulator(new Accumulation(AggregateFunction::BitOr, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer(Field::LongLong, 21, true)));
        $xor = new Accumulator(new Accumulation(AggregateFunction::BitXor, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer(Field::LongLong, 21, true)));
        $and->add(new Frame($context, [6]));
        $and->add(new Frame($context, [3]));
        $or->add(new Frame($context, [6]));
        $or->add(new Frame($context, [3]));
        $xor->add(new Frame($context, [6]));
        $xor->add(new Frame($context, [3]));

        self::assertSame([2, 7, 5], [$and->result(new Frame($context)), $or->result(new Frame($context)), $xor->result(new Frame($context))]);
    }

    public function testExtremeKeepsAValueOrderedBeforeTheKeptOneForMinimum(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Minimum, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer()));
        $accumulator->add(new Frame($context, [5]));
        $accumulator->add(new Frame($context, [-2]));
        $accumulator->add(new Frame($context, [9]));

        self::assertSame(-2, $accumulator->result(new Frame($context)));
    }

    public function testTotalAddsInExactDecimalArithmeticForADecimalSum(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(65, 0)));
        $accumulator->add(new Frame($context, [3]));
        $accumulator->add(new Frame($context, [4]));

        self::assertSame('7', $accumulator->result(new Frame($context)));
    }

    public function testBitsCombinesAnOperandWithTheBitsFoldedSoFar(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::BitAnd, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer(Field::LongLong, 21, true)));
        $accumulator->add(new Frame($context, [12]));
        $accumulator->add(new Frame($context, [10]));

        self::assertSame(8, $accumulator->result(new Frame($context)));
    }

    public function testMomentFoldsTheSquaredDeviationsFromTheRunningMean(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Variance, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $accumulator->add(new Frame($context, [2]));
        $accumulator->add(new Frame($context, [6]));

        self::assertSame(4.0, $accumulator->spread(false));
    }

    public function testConcatenateCollectsTheTextOfTheArgumentsOfARow(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $text = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));
        $accumulator = new Accumulator(new Accumulation(null, [new ColumnRead($text, 0), new ColumnRead(Domain::integer(), 1)], false, $text));
        $accumulator->concatenate(new Frame($context), ['a', 1]);
        $accumulator->concatenate(new Frame($context), ['b', 2]);

        self::assertSame('a1,b2', $accumulator->result(new Frame($context)));
    }

    public function testResultOfTheBitAggregatesOfNoValueIsTheirIdentity(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $unsigned = Domain::integer(Field::LongLong, 21, true);

        self::assertSame(
            [-1, 0, 0],
            [
                (new Accumulator(new Accumulation(AggregateFunction::BitAnd, [new ColumnRead(Domain::integer(), 0)], false, $unsigned)))->result(new Frame($context)),
                (new Accumulator(new Accumulation(AggregateFunction::BitOr, [new ColumnRead(Domain::integer(), 0)], false, $unsigned)))->result(new Frame($context)),
                (new Accumulator(new Accumulation(AggregateFunction::BitXor, [new ColumnRead(Domain::integer(), 0)], false, $unsigned)))->result(new Frame($context)),
            ],
        );
    }

    public function testResultOfSumMinimumAndMaximumOfNoValueIsNull(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(
            [null, null, null, 0],
            [
                (new Accumulator(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(65, 0))))->result(new Frame($context)),
                (new Accumulator(new Accumulation(AggregateFunction::Minimum, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer())))->result(new Frame($context)),
                (new Accumulator(new Accumulation(AggregateFunction::Maximum, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer())))->result(new Frame($context)),
                (new Accumulator(new Accumulation(AggregateFunction::Count, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer())))->result(new Frame($context)),
            ],
        );
    }

    public function testResultRoundsADecimalSumToTheScaleOfItsDomain(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Sum, [new ColumnRead(Domain::decimal(3, 1), 0)], false, Domain::decimal(65, 1)));
        $accumulator->add(new Frame($context, ['1.5']));
        $accumulator->add(new Frame($context, ['2.0']));

        self::assertSame('3.5', $accumulator->result(new Frame($context)));
    }

    public function testAverageDividesTheDecimalSumToTheScaleOfItsDomain(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Average, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(14, 4)));
        $accumulator->add(new Frame($context, [3]));
        $accumulator->add(new Frame($context, [1]));
        $accumulator->add(new Frame($context, [3]));

        self::assertSame('2.3333', $accumulator->average());
    }

    public function testAverageOfDoublesIsADouble(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulator = new Accumulator(new Accumulation(AggregateFunction::Average, [new ColumnRead(Domain::double(), 0)], false, Domain::double()));
        $accumulator->add(new Frame($context, [1.0]));
        $accumulator->add(new Frame($context, [2.0]));

        self::assertSame(1.5, $accumulator->average());
    }

    public function testAverageOfNoValueIsNull(): void
    {
        self::assertNull((new Accumulator(new Accumulation(AggregateFunction::Average, [new ColumnRead(Domain::integer(), 0)], false, Domain::decimal(14, 4))))->average());
    }

    public function testSpreadAnswersThePopulationVarianceAndStandardDeviation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $variance = new Accumulator(new Accumulation(AggregateFunction::Variance, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $deviation = new Accumulator(new Accumulation(AggregateFunction::StandardDeviation, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $variance->add(new Frame($context, [2]));
        $variance->add(new Frame($context, [4]));
        $variance->add(new Frame($context, [4]));
        $variance->add(new Frame($context, [6]));
        $deviation->add(new Frame($context, [2]));
        $deviation->add(new Frame($context, [4]));
        $deviation->add(new Frame($context, [4]));
        $deviation->add(new Frame($context, [6]));

        self::assertSame([2.0, sqrt(2.0)], [$variance->spread(false), $deviation->spread(true)]);
    }

    public function testSpreadAnswersTheSampleVarianceAndStandardDeviation(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $variance = new Accumulator(new Accumulation(AggregateFunction::SampleVariance, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $deviation = new Accumulator(new Accumulation(AggregateFunction::SampleStandardDeviation, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $variance->add(new Frame($context, [1]));
        $variance->add(new Frame($context, [3]));
        $deviation->add(new Frame($context, [1]));
        $deviation->add(new Frame($context, [3]));

        self::assertSame([2.0, sqrt(2.0)], [$variance->result(new Frame($context)), $deviation->result(new Frame($context))]);
    }

    public function testSpreadOfASampleOfOneValueIsNull(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $sample = new Accumulator(new Accumulation(AggregateFunction::SampleVariance, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $population = new Accumulator(new Accumulation(AggregateFunction::Variance, [new ColumnRead(Domain::integer(), 0)], false, Domain::double()));
        $sample->add(new Frame($context, [5]));
        $population->add(new Frame($context, [5]));

        self::assertSame([null, 0.0], [$sample->spread(false), $population->spread(false)]);
    }

    public function testConcatenationJoinsTheValuesInTheOrderOfTheKeys(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));
        $ascending = new Accumulator(new Accumulation(null, [new ColumnRead($text, 0)], false, $text, [[new ColumnRead($text, 0), false]], '-'));
        $descending = new Accumulator(new Accumulation(null, [new ColumnRead($text, 0)], false, $text, [[new ColumnRead($text, 0), true]], '-'));
        $ascending->add(new Frame($context, ['b']));
        $ascending->add(new Frame($context, [null]));
        $ascending->add(new Frame($context, ['a']));
        $ascending->add(new Frame($context, ['c']));
        $descending->add(new Frame($context, ['b']));
        $descending->add(new Frame($context, ['a']));
        $descending->add(new Frame($context, ['c']));

        self::assertSame(['a-b-c', 'c-b-a'], [$ascending->concatenation(new Frame($context)), $descending->concatenation(new Frame($context))]);
    }

    public function testConcatenationCutsTheResultAtTheLengthLimitAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $text = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'));
        $accumulator = new Accumulator(new Accumulation(null, [new ColumnRead($text, 0)], false, $text, [], ',', 5));
        $accumulator->add(new Frame($context, ['abc']));
        $accumulator->add(new Frame($context, ['def']));

        self::assertSame('abc,d', $accumulator->concatenation(new Frame($context)));
        self::assertSame([1260], array_column($session->diagnostics->conditions, 1));
    }

    public function testConcatenationOfNoValueIsNull(): void
    {
        $session = (new Instance())->connect();
        $text = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertNull((new Accumulator(new Accumulation(null, [new ColumnRead($text, 0)], false, $text)))->concatenation(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testConcatenationJoinsInTheCharacterSetOfTheResult(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (u VARCHAR(4) CHARACTER SET utf16, k INT); INSERT INTO t VALUES ('é12', 1), ('34', 2)");
        $result = $session->query("SELECT GROUP_CONCAT(u ORDER BY k SEPARATOR 'ü'), HEX(GROUP_CONCAT(u ORDER BY k)) FROM t")[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['é12ü34', '00E900310032002C00330034']], $result->rows);
    }

    public function testCollectKeepsNullsAndRefusesANullName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE s (id INT, j JSON)');
        $session->query("INSERT INTO s VALUES (1, '1'), (2, NULL)");
        $result = $session->query('SELECT JSON_ARRAYAGG(j), JSON_OBJECTAGG(id, j), JSON_ARRAYAGG(j) FROM s WHERE id > 5')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
        $all = $session->query('SELECT JSON_ARRAYAGG(j) FROM s')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $all);
        self::assertSame([['[1, null]']], $all->rows);
        $this->expectExceptionMessage('JSON documents may not contain NULL member names.');
        $session->query('SELECT JSON_OBJECTAGG(j, id) FROM s');
    }
}
