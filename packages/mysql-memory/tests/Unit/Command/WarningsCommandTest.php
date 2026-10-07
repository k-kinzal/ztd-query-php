<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\WarningsCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

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
}
