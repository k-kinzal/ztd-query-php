<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ShowCreateProgramCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowCreateProgramCommand::class)]
#[Small]
final class ShowCreateProgramCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCreateProgramCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheCharacteristicsInTheirOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE PROCEDURE p1() COMMENT 'c' SQL SECURITY INVOKER DETERMINISTIC NO SQL LANGUAGE SQL SELECT 1");

        $result = $session->query('SHOW CREATE PROCEDURE p1')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame("CREATE DEFINER=`root`@`%` PROCEDURE `p1`()\n    NO SQL\n    DETERMINISTIC\n    SQL SECURITY INVOKER\n    COMMENT 'c'\nSELECT 1", $result->rows[0][2]);
        self::assertSame([256, 468, 4096], [$result->columns[0]->length, $result->columns[1]->length, $result->columns[2]->length]);
    }

    public function testExecuteWritesTheTypeAFunctionReturns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('create function f (x int) returns int deterministic return x*2');

        $result = $session->query('SHOW CREATE FUNCTION f')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame("CREATE DEFINER=`root`@`%` FUNCTION `f`(x int) RETURNS int\n    DETERMINISTIC\nreturn x*2", $result->rows[0][2]);
    }

    public function testExecuteNamesAMissingRoutineWithoutItsDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('PROCEDURE x does not exist');

        $session->query('SHOW CREATE PROCEDURE IMPORT.`x`');
    }

    public function testExecuteWritesATrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER c4 BEFORE INSERT ON t FOR EACH ROW SET @a = 4');

        $result = $session->query('SHOW CREATE TRIGGER C4')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame('CREATE DEFINER=`root`@`%` TRIGGER `c4` BEFORE INSERT ON `t` FOR EACH ROW SET @a = 4', $result->rows[0][2]);
    }

    public function testExecuteRefusesAMissingTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1360);

        $session->query('SHOW CREATE TRIGGER RESTART');
    }

    public function testExecuteWritesAnEventAsLongAsItsText(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE EVENT e2 ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 2");

        $result = $session->query('SHOW CREATE EVENT e2')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        $text = "CREATE DEFINER=`root`@`%` EVENT `e2` ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 2";

        self::assertSame([$text, strlen($text) * 4], [$result->rows[0][3], $result->columns[3]->length]);
    }

    public function testExecuteRefusesAMissingEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1539);
        $this->expectExceptionMessage("Unknown event 'name'");

        $session->query('SHOW CREATE EVENT UNKNOWN .`name`');
    }
}
