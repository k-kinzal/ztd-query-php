<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Generator;
use MySqlMemory\Evaluation\Function\Math\Numerics;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Numerics::class)]
#[Small]
final class NumericsTest extends TestCase
{
    public function testRoutinesNamesTheFunctions(): void
    {
        self::assertSame(['MOD', 'ATAN', 'ATAN2', 'CRC32', 'BIT_COUNT', 'RAND'], array_map(static fn ($routine): string => $routine->name, (new Numerics())->routines()));
    }

    public function testRoutinesMakeModTheModuloOperator(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT MOD(7, 3), MOD(-7, 3), MOD(7.5, 2), MOD(7, 0)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1', '-1', '1.5', null]], $result->rows);
        self::assertSame([[Field::LongLong, 2], [Field::NewDecimal, 4]], [[$result->columns[0]->type, $result->columns[0]->length], [$result->columns[2]->type, $result->columns[2]->length]]);
        self::assertSame([['Warning', '1365', 'Division by 0']], $warnings->rows);
    }

    public function testArcTangentTakesTheQuadrantOfTwoArguments(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ATAN(1, 2), ATAN2(-1, -2), ATAN2(1), ATAN2(NULL, 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.4636476090008061', '-2.677945044588987', '0.7853981633974483', null]], $result->rows);
    }

    public function testChecksumChecksTheTextOfTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CRC32('a'), CRC32(1.5), CRC32(CONVERT('é' USING latin1)), CRC32(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3904355907', '2270993338', '198489425', null]], $result->rows);
        self::assertSame([Field::LongLong, 10], [$result->columns[0]->type, $result->columns[0]->length]);
    }

    public function testBitCountCountsTheBitsOfIntegersAndBinaryStrings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT BIT_COUNT(7), BIT_COUNT(-1), BIT_COUNT(2.5), BIT_COUNT(_binary'abc'), BIT_COUNT('7x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['3', '64', '2', '10', '3']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: '7x'"]], $warnings->rows);
    }

    public function testBitCountReadsABinaryStringAsANumberIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT BIT_COUNT(_binary'abc')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertSame(2, $result->columns[0]->length);
    }

    public function testIntegerClampsADecimalBeyondTheRangeWithAWarning(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertSame([PHP_INT_MAX, 7], [(new Numerics())->integer('99999999999999999999', Domain::of(Resolved::decimal(20, 0), true), $context), (new Numerics())->integer(7, Domain::of(Resolved::integer(), true), $context)]);
        self::assertCount(1, $context->diagnostics->conditions);
    }

    public function testRandomFollowsTheSequenceOfTheSeed(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query('SELECT RAND(1), RAND(a), RAND(NULL) FROM t')[0];
        $unseeded = $session->query('SELECT RAND() >= 0 AND RAND() < 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $unseeded);
        self::assertSame([
            ['0.40540353712197724', '0.40540353712197724', '0.15522042769493574'],
            ['0.8716141803857071', '0.6555866465490187', '0.620881741513388'],
            ['0.1418603212962489', '0.9057697559760601', '0.6387474552157777'],
        ], $result->rows);
        self::assertSame([['1']], $unseeded->rows);
    }

    public function testSeedWrapsTheArgumentInAGenerator(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $seeded = (new Numerics())->seed($frame, [new Constant(Domain::of(Resolved::integer(), false), 1)], [true]);

        self::assertInstanceOf(Generator::class, $seeded[0]);
        self::assertTrue($seeded[0]->constant);
        self::assertSame([], (new Numerics())->seed($frame, [], []));
    }
}
