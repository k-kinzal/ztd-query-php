<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\LoadDataCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadDataCommand::class)]
#[Small]
final class LoadDataCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new LoadDataCommand())->clearsDiagnostics());
    }

    public function testExecuteRefusesSeveralFilesBeforeTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of LOAD DATA without BULK Algorithm and multiple files');

        $session->query("LOAD DATA FROM LOCAL URL 'x' COUNT 1 INTO TABLE nope");
    }

    public function testExecuteRefusesLocalData(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3948);

        $session->query("LOAD DATA LOCAL INFILE 'x' INTO TABLE nope");
    }

    public function testExecuteRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        $session->query("LOAD DATA INFILE 'x' INTO TABLE abc.t");
    }

    public function testExecuteRefusesAFileOutsideTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1290);
        $this->expectExceptionMessage('The MySQL server is running with the --secure-file-priv option so it cannot execute this statement');

        $session->query("LOAD XML INFILE 'x' INTO TABLE t");
    }

    public function testExecuteFindsNoFileInTheSecureDirectory(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(13);
        $this->expectExceptionMessage("Can't get stat of '/var/lib/mysql-files/x' (OS errno 2 - No such file or directory)");

        $session->query("LOAD DATA INFILE '/var/lib/mysql-files/x' INTO TABLE t");
    }
}
