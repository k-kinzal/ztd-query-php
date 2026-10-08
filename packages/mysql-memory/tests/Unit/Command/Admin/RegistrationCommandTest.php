<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\RegistrationCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegistrationCommand::class)]
#[Small]
final class RegistrationCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RegistrationCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesTheRegistrationOfAnotherAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4060);
        $this->expectExceptionMessage("The registration operation is not allowed for user 'root'@'%'. The operation can only be performed in user's own session.");

        $session->query("ALTER USER someone@'BvjX+b' 3 FACTOR INITIATE REGISTRATION");
    }

    public function testExecuteFindsNoFactorOfTheSessionAccount(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4057);
        $this->expectExceptionMessage("2 factor authentication method doesn't exist. Please do ALTER USER... ADD 2 factor... before doing this operation.");

        $session->query('ALTER USER USER() 2 FACTOR INITIATE REGISTRATION');
    }

    public function testExecuteFindsNoPluginToUnregister(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1524);
        $this->expectExceptionMessage("Plugin '' is not loaded");

        $session->query('ALTER USER someone 3 FACTOR UNREGISTER');
    }
}
