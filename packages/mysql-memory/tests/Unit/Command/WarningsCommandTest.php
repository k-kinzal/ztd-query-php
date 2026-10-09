<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\WarningsCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;

#[CoversClass(WarningsCommand::class)]
#[Small]
final class WarningsCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersFalse(): void
    {
        self::assertFalse((new WarningsCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheConditionsOfTheLastStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query("DO 1 + 'a', 2 + 'b'");

        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $result->rows);
        self::assertSame(
            [['Level', Field::VarString], ['Code', Field::Long], ['Message', Field::VarString]],
            [[$result->columns[0]->name, $result->columns[0]->type], [$result->columns[1]->name, $result->columns[1]->type], [$result->columns[2]->name, $result->columns[2]->type]],
        );
    }

    public function testExecuteListsOnlyTheErrorsForShowErrors(): void
    {
        $session = (new Instance())->connect();
        $session->query("DO 1 + 'a'");

        $result = $session->query('SHOW ERRORS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testExecuteListsTheErrorOfAFailedStatement(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT nope');

        $errors = $session->query('SHOW ERRORS')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $errors);
        self::assertSame([['Error', '1054', "Unknown column 'nope' in 'field list'"]], $errors->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1054', "Unknown column 'nope' in 'field list'"]], $warnings->rows);
    }

    public function testExecuteDescribesTheColumnsAsTheServerDoes(): void
    {
        $session = (new Instance())->connect();
        $session->query('DO 1/0');

        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(
            [[28, 31, 255], [5, 0, 63], [2048, 31, 255]],
            [[$result->columns[0]->length, $result->columns[0]->decimals, $result->columns[0]->charset], [$result->columns[1]->length, $result->columns[1]->decimals, $result->columns[1]->charset], [$result->columns[2]->length, $result->columns[2]->decimals, $result->columns[2]->charset]],
        );
        self::assertSame(ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value, $result->columns[1]->flags);
    }

    public function testExecuteSendsTheTextColumnsInTheCharacterSetOfTheResults(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET NAMES latin1');

        $result = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[7, 8], [512, 8]], [[$result->columns[0]->length, $result->columns[0]->charset], [$result->columns[2]->length, $result->columns[2]->charset]]);
    }

    public function testExecuteListsTheConditionsTheLimitSelects(): void
    {
        $session = (new Instance())->connect();
        $session->query("DO 1 + 'a', 2 + 'b', 3 + 'c'");

        $limited = $session->query('SHOW WARNINGS LIMIT 1, 1')[0];
        $none = $session->query('SHOW WARNINGS LIMIT 0')[0];

        self::assertInstanceOf(ResultSet::class, $limited);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $limited->rows);
        self::assertInstanceOf(ResultSet::class, $none);
        self::assertSame([], $none->rows);
    }

    public function testExecuteCountsTheConditionsForShowCount(): void
    {
        $session = (new Instance())->connect();
        $session->query("DO 1 + 'a', 2 + 'b'");

        $warnings = $session->query('SHOW COUNT(*) WARNINGS')[0];
        $errors = $session->query('SHOW COUNT(*) ERRORS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['@@session.warning_count', Field::LongLong, 21]], [[$warnings->columns[0]->name, $warnings->columns[0]->type, $warnings->columns[0]->length]]);
        self::assertSame([['2']], $warnings->rows);
        self::assertInstanceOf(ResultSet::class, $errors);
        self::assertSame([['0']], $errors->rows);
    }

    public function testBoundReadsAnIntegerLiteral(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SHOW WARNINGS LIMIT 2, 3')->statement;
        self::assertInstanceOf(ShowWarnings::class, $statement);
        self::assertInstanceOf(RowLimit::class, $statement->limit);

        self::assertSame([3, 2, null], [(new WarningsCommand())->bound($statement->limit->count), (new WarningsCommand())->bound($statement->limit->offset), (new WarningsCommand())->bound(null)]);
    }

    public function testExecuteRefusesALimitNamingAVariableAndKeepsTheConditions(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT 1 + 'a'");
        $error = $session->run('SHOW WARNINGS LIMIT abc')[0];

        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([1327, 'Undeclared variable: abc'], [$error->getCode(), $error->getMessage()]);
        self::assertSame(['1292', '1327'], array_column($warnings->rows, 1));
    }

    public function testExecuteCountsTheWarningsBeyondMaxErrorCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET max_error_count = 1');
        $session->query("SELECT 'a' + 0, 'b' + 0");
        $result = $session->query('SHOW COUNT(*) WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }
}
