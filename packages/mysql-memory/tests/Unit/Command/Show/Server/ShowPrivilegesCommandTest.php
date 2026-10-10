<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\ShowPrivilegesCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowPrivilegesCommand::class)]
#[Small]
final class ShowPrivilegesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowPrivilegesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsThePrivileges(): void
    {
        $result = (new Instance())->connect()->query('SHOW PRIVILEGES')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['Alter', 'Tables', 'To alter the table'], $result->rows[0]);
        self::assertContains(['ROLE_ADMIN', 'Server Admin', ''], $result->rows);
        self::assertSame([40, 31], [$result->columns[0]->length, $result->columns[0]->decimals]);
    }

    public function testExecuteListsThePrivilegesOfTheRelease(): void
    {
        $result = (new Instance('8.0.44'))->connect()->query('SHOW PRIVILEGES')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([69, true, false], [count($result->rows), in_array('SET_USER_ID', array_column($result->rows, 0), true), in_array('SET_ANY_DEFINER', array_column($result->rows, 0), true)]);
    }
}
