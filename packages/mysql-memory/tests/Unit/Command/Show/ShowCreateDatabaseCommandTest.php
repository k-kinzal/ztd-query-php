<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ShowCreateDatabaseCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowCreateDatabaseCommand::class)]
#[Small]
final class ShowCreateDatabaseCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCreateDatabaseCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheStatementOfTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE l CHARACTER SET latin1; CREATE DATABASE u COLLATE utf8mb4_bin');

        $default = $session->query('SHOW CREATE DATABASE IF NOT EXISTS d')[0];
        $latin1 = $session->query('SHOW CREATE SCHEMA l')[0];
        $binary = $session->query('SHOW CREATE DATABASE u')[0];
        $schema = $session->query('SHOW CREATE DATABASE information_schema')[0];

        self::assertInstanceOf(ResultSet::class, $default);
        self::assertInstanceOf(ResultSet::class, $latin1);
        self::assertInstanceOf(ResultSet::class, $binary);
        self::assertInstanceOf(ResultSet::class, $schema);
        self::assertSame([['d', "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `d` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */"]], $default->rows);
        self::assertSame("CREATE DATABASE `l` /*!40100 DEFAULT CHARACTER SET latin1 */ /*!80016 DEFAULT ENCRYPTION='N' */", $latin1->rows[0][1]);
        self::assertSame("CREATE DATABASE `u` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin */ /*!80016 DEFAULT ENCRYPTION='N' */", $binary->rows[0][1]);
        self::assertSame("CREATE DATABASE `information_schema` /*!40100 DEFAULT CHARACTER SET utf8mb3 */ /*!80016 DEFAULT ENCRYPTION='N' */", $schema->rows[0][1]);
    }

    public function testExecuteRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'GLOBAL'");

        $session->query('SHOW CREATE SCHEMA IF NOT EXISTS GLOBAL');
    }
}
