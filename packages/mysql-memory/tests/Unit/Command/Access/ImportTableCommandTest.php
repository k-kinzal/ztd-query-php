<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\ImportTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportTableCommand::class)]
#[Small]
final class ImportTableCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ImportTableCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesAFileOutsideTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1290);
        $this->expectExceptionMessage("The MySQL server is running with the --secure-file-priv='/var/lib/mysql-files/' option so it cannot execute this statement");

        $session->query("IMPORT TABLE FROM 'x', '/var/lib/mysql-files/x.sdi'");
    }

    public function testExecuteFindsNoFileInTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3608);
        $this->expectExceptionMessage("No SDI files matched the pattern 'x.sdi'");

        $session->query("IMPORT TABLE FROM '/var/lib/mysql-files/x.sdi', 'x'");
    }
}
