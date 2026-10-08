<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\ProgramProblems;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(ProgramProblems::class)]
#[Small]
final class ProgramProblemsTest extends TestCase
{
    public function testStoresTellsTheStatementsThatStoreABody(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, false], [ProgramProblems::stores($session->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1')->statement), ProgramProblems::stores($session->analyze('SELECT 1')->statement)]);
    }

    public function testParameterTellsAnUndeclaredVariableTheRoutineDeclares(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE PROCEDURE p(n INT) SELECT 1 LIMIT n')->statement;

        self::assertSame([true, false], [ProgramProblems::parameter($statement, new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable(new Name('N'))), ProgramProblems::parameter($statement, new \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable(new Name('m')))]);
    }

    public function testRaiseRaisesTheProblemsOfTheRulesFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1363);
        $this->expectExceptionMessage('There is no NEW row in on DELETE trigger');

        (new ProgramProblems())->raise($session->analyze('CREATE TRIGGER q BEFORE DELETE ON t FOR EACH ROW SET @a = NEW.a'), $session, new Problems());
    }

    public function testRaiseNamesAFunctionWithoutReturnWithItsDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1320);
        $this->expectExceptionMessage('No RETURN found in FUNCTION d.i');

        $session->query('CREATE FUNCTION i() RETURNS INT DETERMINISTIC BEGIN END');
    }

    public function testRaiseRefusesAStoredFunctionInTheScheduleOfAnEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage("This version of MySQL doesn't yet support 'Usage of subqueries or stored function calls as part of this statement'");

        $session->query('CREATE EVENT e9 ON SCHEDULE AT nope() DO SELECT 1');
    }

    public function testRaiseRefusesAVariableInAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1351);

        $session->query('CREATE VIEW v AS SELECT @x FROM nope');
    }

    public function testRaiseResolvesTheQueryOfAViewBeforeItsName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage("Table 'd.nope' doesn't exist");

        $session->query('ALTER VIEW BEGIN.LOCAL AS SELECT * FROM nope');
    }

    public function testRaiseLeavesOtherStatementsToTheirProblems(): void
    {
        $session = (new Instance())->connect();

        self::assertFalse((new ProgramProblems())->raise($session->analyze('SELECT 1'), $session, new Problems()));
    }

    public function testAccountRefusesAUserNameOfMoreThan32Characters(): void
    {
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('a', 33) . "' is too long for user name (should be no longer than 32)");

        (new ProgramProblems())->account(new AccountName(new Name(str_repeat('a', 33))));
    }

    public function testParsedKeepsTheRulesAndTheRowColumnsOfATrigger(): void
    {
        $problems = new ProgramProblems();
        $row = new MissingColumn(new Name('zz'), new QualifiedName(new Name('NEW')));

        self::assertSame([true, true, false], [$problems->parsed(new ProgramProblem(ProgramRule::ReturnOutsideFunction), false), $problems->parsed($row, true), $problems->parsed($row, false)]);
    }

    public function testTriggersRefusesADatabaseThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SHOW TRIGGERS FROM nope WHERE x = 1')->statement;
        self::assertInstanceOf(ShowTriggers::class, $statement);

        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        (new ProgramProblems())->triggers($statement, $session);
    }

    public function testDefinerRefusesAHostNameOfMoreThan255CharactersOfAView(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze("CREATE DEFINER = 'u'@'" . str_repeat('h', 256) . "' VIEW v AS SELECT 1")->statement;

        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('h', 256) . "' is too long for host name (should be no longer than 255)");

        (new ProgramProblems())->definer($statement);
    }

    public function testViewRefusesAParameterInTheQuery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $operation = $session->analyze('CREATE VIEW v AS SELECT ?', true);
        $statement = $operation->statement;
        self::assertInstanceOf(CreateView::class, $statement);

        $this->expectExceptionCode(1351);

        (new ProgramProblems())->view($statement, $operation, $session, new Problems());
    }

    public function testScheduleRefusesATableInTheScheduleOfAnEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT (SELECT NOW() FROM t) DO SELECT 1');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);

        $this->expectExceptionCode(1235);

        (new ProgramProblems())->schedule($statement, $operation);
    }

    public function testBodyRefusesAnUnknownColumnOfTheNewRowOfATrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('CREATE TRIGGER q BEFORE INSERT ON t FOR EACH ROW SET @a = NEW.zz');

        $this->expectExceptionCode(1054);
        $this->expectExceptionMessage("Unknown column 'zz' in 'NEW'");

        (new ProgramProblems())->body($operation->statement, $operation, $session, new Problems());
    }
}
