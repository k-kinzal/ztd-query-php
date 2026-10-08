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
}
