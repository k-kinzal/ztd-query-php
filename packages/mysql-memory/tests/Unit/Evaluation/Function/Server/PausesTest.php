<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Server;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Server\Pauses;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Pauses::class)]
#[Small]
final class PausesTest extends TestCase
{
    public function testRoutinesNameSleepAndBenchmark(): void
    {
        self::assertSame(['SLEEP', 'BENCHMARK'], array_map(static fn ($routine): string => $routine->name, (new Pauses())->routines()));
    }

    public function testSleepPassesTheDurationOnTheClockOfTheServer(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $result = $session->query('SELECT SLEEP(1800), SYSDATE(6) >= NOW(6) + INTERVAL 1799 SECOND, SLEEP(1800)')[0];
        $later = $session->query('SELECT NOW() >= SYSDATE() - INTERVAL 1 SECOND')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $later);
        self::assertSame([[['0', '1', '0']], [['1']]], [$result->rows, $later->rows]);
        self::assertSame(3600.0, $instance->registry->threads->passed);
        self::assertSame([21, Field::LongLong], [$result->columns[0]->length, $result->columns[0]->type]);
    }

    public function testSleepReadsAStringAsANumberWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT SLEEP('a')");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testSleepRefusesANegativeDuration(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to sleep.');
        $session->query("SELECT SLEEP(' -0.5')");
    }

    public function testSleepAnswers0ForNullInMySql56(): void
    {
        $result = (new Instance('5.6.51'))->connect()->query('SELECT SLEEP(NULL), SLEEP(-1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0']], $result->rows);
    }

    public function testBenchmarkEvaluatesTheExpressionCountTimes(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x = 0');
        $result = $session->query('SELECT BENCHMARK(5, @x := @x + 1), @x, BENCHMARK(NULL, 1), BENCHMARK(0, 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '5', null, '0']], $result->rows);
    }

    public function testBenchmarkRepeatsTheWarningsOfEachEvaluation(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT BENCHMARK(3, 'a' + 1)");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(array_fill(0, 3, ['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]), $warnings->rows);
    }

    public function testBenchmarkWarnsOfANegativeCount(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT BENCHMARK(' -1', 1 / 0)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null]], $result->rows);
        self::assertSame([['Warning', '1411', "Incorrect count value: '-1' for function benchmark"]], $warnings->rows);
    }

    public function testBenchmarkLetsEverySleepPass(): void
    {
        $instance = new Instance();
        $instance->connect()->query('SELECT BENCHMARK(4, SLEEP(0.5))');

        self::assertSame(2.0, $instance->registry->threads->passed);
    }

    public function testPureTellsAnExpressionWithoutEffects(): void
    {
        $one = new Constant(Domain::integer(Field::LongLong, 1), 1);
        $seen = [];
        $none = [];
        $nested = [];

        self::assertSame([true, false, false], [(new Pauses())->pure($one, $seen), (new Pauses())->pure(new Assignment('x', $one, Domain::integer()), $none), (new Pauses())->pure(new \MySqlMemory\Evaluation\Operator\Minus(new Assignment('x', $one, Domain::integer()), Domain::integer(), '-x'), $nested)]);
    }
}
