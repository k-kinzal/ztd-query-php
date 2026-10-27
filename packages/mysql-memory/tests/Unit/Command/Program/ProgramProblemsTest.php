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
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(ProgramProblems::class)]
#[Small]
final class ProgramProblemsTest extends TestCase
{
    public function testScheduleRefusesAnArrayCastBeforeEvaluatingTheTime(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1235);
        $this->expectExceptionMessage(\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast::ARRAY_OUTSIDE_INDEX);

        $session->query('CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1');
    }

    public function testStoresTellsTheStatementsThatStoreABody(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([true, false], [ProgramProblems::stores($session->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1')), ProgramProblems::stores($session->analyze('SELECT 1'))]);
    }

    public function testRaiseConsumesScopedVariableResolutionFromSemantics(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('CREATE PROCEDURE p(n INT) SELECT 1 LIMIT n');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        self::assertSame([], $session->analyze('CREATE PROCEDURE p(n INT) SELECT 1 LIMIT N')->statementInformation()->diagnostics);
        self::assertSame(['Undeclared variable: m'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), $session->analyze('CREATE PROCEDURE p(n INT) SELECT 1 LIMIT m')->statementInformation()->diagnostics));
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

    public function testScheduleRefusesADeclaredStoredFunctionAsWellAsAnAbsentOne(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE FUNCTION schedule_time() RETURNS DATETIME DETERMINISTIC RETURN '2030-01-01'");
        $this->expectExceptionCode(1235);
        $session->query('ALTER EVENT e ON SCHEDULE AT schedule_time()');
    }

    public function testRaiseRefusesAVariableInAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1351);

        $session->query('CREATE VIEW v AS SELECT @x FROM nope');
    }

    public function testViewResolvesTheQueryBeforeItsTargetName(): void
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

    public function testDefinerAppliesTheSelectedLanguageLimitToAConstructibleAccount(): void
    {
        $statement = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new QualifiedName(new Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new ShowTriggers(false), definer: new AccountName(new Name(str_repeat('a', 17))));
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('a', 17) . "' is too long for user name (should be no longer than 16)");

        (new ProgramProblems())->definer($statement, \SqlSemantics\Contract\GrammarRelease::MySql5651);
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
        $statement = $session->analyze('SHOW TRIGGERS FROM nope WHERE x = 1');
        self::assertInstanceOf(ShowTriggers::class, $statement);

        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nope'");

        (new ProgramProblems())->triggers($statement, $session);
    }

    public function testTriggersDefersALegacyDatabaseLookupUntilRowsAreRead(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query('SHOW TRIGGERS FROM absent WHERE 0')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testInputRefusesAHostNameOfMoreThan255BytesOfAView(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1470);
        $this->expectExceptionMessage("String '" . str_repeat('h', 70) . "' is too long for host name (should be no longer than 255)");

        $session->query("CREATE DEFINER = 'u'@'" . str_repeat('h', 256) . "' VIEW v AS SELECT 1");
    }

    public function testScheduleRefusesATableInTheScheduleOfAnEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT (SELECT NOW() FROM t) DO SELECT 1');
        $statement = $operation;
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

        (new ProgramProblems())->body($operation, $operation, $session, new Problems());
    }

}
