<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ShowCreateViewCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowCreateViewCommand::class)]
#[Small]
final class ShowCreateViewCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCreateViewCommand())->clearsDiagnostics());
    }

    public function testExecuteWritesTheQueryAsTheServerStoresIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');
        $session->query('CREATE VIEW v AS SELECT a, b, a+1 AS c FROM t WITH LOCAL CHECK OPTION');

        $result1 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        $row = $result1->rows[0];

        self::assertSame(['v', 'CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v` AS select `t`.`a` AS `a`,`t`.`b` AS `b`,(`t`.`a` + 1) AS `c` from `t` WITH LOCAL CHECK OPTION', 'utf8mb4', 'utf8mb4_0900_ai_ci'], $row);
    }

    public function testExecuteQualifiesTheNamesOfAnotherDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('CREATE DATABASE e');
        $session->query('USE e');

        $result2 = $session->query('SHOW CREATE VIEW d.v')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        $text = $result2->rows[0][1];

        self::assertSame('CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `d`.`v` AS select `d`.`t`.`a` AS `a` from `d`.`t`', $text);
    }

    public function testExecuteWarnsOfAnInvalidView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT a FROM t');
        $session->query('DROP TABLE t');

        $session->query('SHOW CREATE VIEW v');

        self::assertSame([['Warning', 1356, "View 'd.v' references invalid table(s) or column(s) or function(s) or definer/invoker of view lack rights to use them"]], $session->diagnostics->conditions);
    }

    public function testWriteAnswersTheStatementOfAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE VIEW v AS SELECT 1 AS x');
        $view = $session->instance->dictionary->schema('d')?->views['v'];
        self::assertNotNull($view);
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $result = (new ShowCreateViewCommand())->write($view, $session, $context);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v` AS select 1 AS `x`', $result->rows[0][1]);
    }

    public function testExecuteRefusesAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'CHECKSUM'");

        $session->query('SHOW CREATE VIEW CHECKSUM .LOCAL');
    }

    public function testExecuteRefusesABaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1347);

        $session->query('SHOW CREATE VIEW t');
    }
}
