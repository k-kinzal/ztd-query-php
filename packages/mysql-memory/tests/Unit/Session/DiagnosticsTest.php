<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Diagnostics::class)]
#[Small]
final class DiagnosticsTest extends TestCase
{
    public function testWarningRecordsTheNumberAndMessage(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(DataError::TruncatedWrongValue, "Truncated incorrect DOUBLE value: 'x'");
        $diagnostics->warning(1105, 'Unknown error');

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'x'"], ['Warning', 1105, 'Unknown error']], $diagnostics->conditions);
    }

    public function testWarningKeepsAtMost64Conditions(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->conditions = array_fill(0, 64, ['Note', 1, 'n']);
        $diagnostics->warning(1105, 'dropped');
        $diagnostics->note(StatementError::UnknownError, 'dropped');

        self::assertSame(64, $diagnostics->count());
        self::assertSame(['Note', 1, 'n'], $diagnostics->conditions[63]);
    }

    public function testWarningOfAStatementIsShownByShowWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT 'x' + 1");
        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $result->rows);
    }

    public function testNoteRecordsANote(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->note(SchemaError::DatabaseExists, "Can't create database 'd'; database exists");

        self::assertSame([['Note', 1007, "Can't create database 'd'; database exists"]], $diagnostics->conditions);
    }

    public function testNoteOfAStatementIsShownByShowWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE DATABASE IF NOT EXISTS d');
        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Note', '1007', "Can't create database 'd'; database exists"]], $result->rows);
    }

    public function testErrorRecordsAnErrorPastTheLimit(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->conditions = array_fill(0, 64, ['Warning', 1, 'w']);
        $diagnostics->error(1146, "Table 'd.t' doesn't exist");

        self::assertSame(65, $diagnostics->count());
        self::assertSame(['Error', 1146, "Table 'd.t' doesn't exist"], $diagnostics->conditions[64]);
    }

    public function testErrorOfAStatementIsShownByShowWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT * FROM nowhere');
        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Error', '1046', 'No database selected']], $result->rows);
    }

    public function testClearForgetsEveryCondition(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(1105, 'w');
        $diagnostics->error(1105, 'e');
        $diagnostics->clear();

        self::assertSame([], $diagnostics->conditions);
        self::assertSame(0, $diagnostics->count());
    }

    public function testSignalRecordsAWarningWithTheItemsItSets(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->signal(1000, 'w', ['RETURNED_SQLSTATE' => '01234', 'TABLE_NAME' => 'tt']);

        self::assertSame([['Warning', 1000, 'w']], $diagnostics->conditions);
        self::assertSame(['01234', 'tt', ''], [$diagnostics->item(0, 'RETURNED_SQLSTATE'), $diagnostics->item(0, 'TABLE_NAME'), $diagnostics->item(0, 'CLASS_ORIGIN')]);
    }

    public function testItemAnswersTheItemsOfAConditionTheServerRaised(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(1365, 'Division by 0');

        self::assertSame(['22012', 'ISO 9075', 'ISO 9075', ''], [$diagnostics->item(0, 'RETURNED_SQLSTATE'), $diagnostics->item(0, 'CLASS_ORIGIN'), $diagnostics->item(0, 'SUBCLASS_ORIGIN'), $diagnostics->item(0, 'TABLE_NAME')]);
    }

    public function testCountCountsTheConditions(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(1105, 'w');
        $diagnostics->note(StatementError::UnknownError, 'n');

        self::assertSame(2, $diagnostics->count());
    }

    public function testRetainKeepsTheConditionsUntilANewOneIsRaised(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(1292, 'old');
        $diagnostics->retain();

        self::assertSame([['Warning', 1292, 'old']], $diagnostics->conditions);

        $diagnostics->note(DataError::DataTruncated, 'new');

        self::assertSame([['Note', 1265, 'new']], $diagnostics->conditions);
    }

    public function testFreshForgetsRetainedConditionsOnly(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->warning(1292, 'kept');
        $diagnostics->fresh();

        self::assertSame(1, $diagnostics->count());

        $diagnostics->retain();
        $diagnostics->fresh();

        self::assertSame(0, $diagnostics->count());
    }
}
