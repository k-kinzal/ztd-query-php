<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\FieldConstants;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FieldConstants::class)]
#[Small]
final class FieldConstantsTest extends TestCase
{
    public function testStoredComparesADatetimeColumnWithTheDatetimeANumberSpells(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (f DATETIME)');
        $session->query("INSERT INTO t VALUES ('2024-01-31 10:20:30'), ('1999-12-31 23:59:59')");
        $result = $session->query('SELECT f < 20300101, f BETWEEN 20240101 AND 20240201, f IN (20240131, 5) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['1', '1', '0'], ['1', '0', '0']], [['Warning', 1292, "Incorrect datetime value: '5' for column 'f' at row 1"]]], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testColumnAnswersTheNameOfAColumnOnly(): void
    {
        $session = (new Instance())->connect();
        $constants = new FieldConstants((new \MySqlMemory\Plan\Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame(['f', null], [$constants->column(new ColumnUse(new Name('f'))), $constants->column(new NumberLiteral('1'))]);
    }

    public function testNumberAnswersTheTextOfAnIntegerOrDecimalLiteral(): void
    {
        $session = (new Instance())->connect();
        $constants = new FieldConstants((new \MySqlMemory\Plan\Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame(['20240131', '1.5', null, null], [$constants->number(new NumberLiteral('20240131')), $constants->number(new NumberLiteral('1.5')), $constants->number(new NumberLiteral('1e5')), $constants->number(new StringLiteral(['1']))]);
    }

    public function testMomentReadsTheDigitsOfADateOrDatetime(): void
    {
        $session = (new Instance())->connect();
        $constants = new FieldConstants((new \MySqlMemory\Plan\Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame([['2024-01-31 00:00:00', false], ['2024-02-31 00:00:00', false], ['1999-12-31 23:59:59', false], [null, false], [null, false], ['2024-01-31 00:00:00', true]], [$constants->moment('240131'), $constants->moment('20240231'), $constants->moment('19991231235959'), $constants->moment('5'), $constants->moment('2030'), $constants->moment('20240131.5')]);
    }

    public function testDigitsWidensATwoDigitYear(): void
    {
        $session = (new Instance())->connect();
        $constants = new FieldConstants((new \MySqlMemory\Plan\Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame([0, 20240131, 19991231, null, 20240131102030, null], [$constants->digits(0), $constants->digits(240131), $constants->digits(991231), $constants->digits(695000), $constants->digits(240131102030), $constants->digits(100)]);
    }

    public function testSpelledChecksTheRangeOfEachPart(): void
    {
        $session = (new Instance())->connect();
        $constants = new FieldConstants((new \MySqlMemory\Plan\Planner($session->analyze('SELECT 1')->statement, $session->analyze('SELECT 1')->facts, $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary))->compiler);

        self::assertSame(['2024-02-31 00:00:00', '1999-12-31 23:59:59', null, null], [$constants->spelled(20240231), $constants->spelled(19991231235959), $constants->spelled(20241301), $constants->spelled(20240131246000)]);
    }
}
