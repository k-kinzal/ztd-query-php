<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\RoutineCommand;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RoutineCommand::class)]
#[Small]
final class RoutineCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RoutineCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesAProcedureWithItsTextAsWritten(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query("CREATE PROCEDURE p(IN a INT, OUT b VARCHAR(10))  COMMENT 'hi'   BEGIN SELECT a; END");
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $routine = $schema->procedures['p'];

        self::assertSame(['IN a INT, OUT b VARCHAR(10)', 'BEGIN SELECT a; END', 'hi', ['root', '%']], [$routine->parameters, $routine->body, $routine->comment, $routine->definer]);
    }

    public function testExecuteRefusesAnExistingRoutineWithoutCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE Pq() SELECT 1');

        $this->expectExceptionCode(1304);
        $this->expectExceptionMessage('PROCEDURE pQ already exists');

        $session->query('CREATE PROCEDURE pQ() SELECT 1');
    }

    public function testExecuteNotesAnExistingRoutineWithIfNotExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() SELECT 1');

        $session->query('CREATE PROCEDURE IF NOT EXISTS p() SELECT 2');

        self::assertSame([['Note', 1304, 'PROCEDURE p already exists']], $session->diagnostics->conditions);
    }

    public function testExecuteRefusesAFunctionUnsafeForTheBinaryLogBeforeLookingUpTheDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1418);

        $session->query('CREATE FUNCTION nodb.g() RETURNS INT RETURN 1');
    }

    public function testExecuteRefusesAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'nodb'");

        $session->query('CREATE FUNCTION nodb.g() RETURNS INT DETERMINISTIC RETURN 1');
    }

    public function testExecuteDefersTheNamesOfTheBody(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query('CREATE PROCEDURE p() SELECT x FROM nope');

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertArrayHasKey('p', $schema->procedures);
    }

    public function testExecuteNotesADefinerThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query("CREATE DEFINER = 'bob'@'host' PROCEDURE p() SELECT 1");

        self::assertSame([['Note', 1449, "The user specified as a definer ('bob'@'host') does not exist"]], $session->diagnostics->conditions);
    }

    public function testAlterChangesTheCharacteristics(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() SELECT 1');

        (new RoutineCommand())->alter(new AlterRoutine(ProgramKind::Procedure, new QualifiedName(new Name('P')), [new RoutineComment(new Text('x')), new DataAccess(AccessLevel::NoSql)]), $session);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $routine = $schema->procedures['p'];

        self::assertSame(['x', 'NO SQL'], [$routine->comment, $routine->access]);
    }

    public function testAlterRefusesAMissingRoutine(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE fz');
        $session->query('USE fz');

        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('FUNCTION fz.name does not exist');

        $session->query('ALTER FUNCTION `name`');
    }

    public function testRoutinesAnswersTheProceduresOrTheFunctions(): void
    {
        $schema = new Schema('d');

        self::assertSame([[], []], [RoutineCommand::routines($schema, true), RoutineCommand::routines($schema, false)]);
    }

    public function testCharacteristicsKeepsTheLastOfEachKind(): void
    {
        $values = (new RoutineCommand())->characteristics([new DataAccess(AccessLevel::ModifiesSqlData), new Determinism(true), new DataAccess(AccessLevel::ContainsSql), new RoutineComment(new Text('a')), new RoutineComment(new Text('b'))], ['CONTAINS SQL', false, 'DEFINER', '']);

        self::assertSame(['CONTAINS SQL', true, 'DEFINER', 'b'], $values);
    }

    public function testReturnsWritesTheCharacterSetAndACollationThatIsNotItsDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f() RETURNS VARCHAR(5) CHARSET latin1 COLLATE latin1_bin NO SQL RETURN 1');
        $session->query('CREATE FUNCTION g() RETURNS BOOL DETERMINISTIC RETURN 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame(['varchar(5) CHARSET latin1 COLLATE latin1_bin', 'tinyint(1)'], [$schema->functions['f']->returns, $schema->functions['g']->returns]);
    }

    public function testRoutineBuildsTheRoutineWithItsTextAndCharacteristics(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->text = "CREATE FUNCTION f(a INT) RETURNS CHAR(2) NO SQL RETURN 'x'";
        $statement = $session->analyze($session->text)->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        $routine = (new RoutineCommand())->routine($statement, $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), $schema, ['NO SQL', true, 'INVOKER', 'c']);

        self::assertSame(['d', 'f', ['root', '%'], 'a INT', 'char(2) CHARSET utf8mb4', "RETURN 'x'", 'NO SQL', true, 'INVOKER', 'c'], [$routine->schema, $routine->name, $routine->definer, $routine->parameters, $routine->returns, $routine->body, $routine->access, $routine->deterministic, $routine->security, $routine->comment]);
    }

    public function testExecuteKeepsTheBodyAndTheReturnTypeAsMySql57WritesThem(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE FUNCTION f() RETURNS CHAR(3) CHARSET utf8 COLLATE utf8_bin DETERMINISTIC RETURN 'a'");
        $session->query('CREATE PROCEDURE q() SELECT 2 /* c */ ;');

        $function = $session->query('SHOW CREATE FUNCTION f')[0];
        $procedure = $session->query('SHOW CREATE PROCEDURE q')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $function);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $procedure);
        self::assertSame("CREATE DEFINER=`root`@`%` FUNCTION `f`() RETURNS char(3) CHARSET utf8 COLLATE utf8_bin\n    DETERMINISTIC\nRETURN 'a'", $function->rows[0][2]);
        self::assertSame("CREATE DEFINER=`root`@`%` PROCEDURE `q`()\nSELECT 2 /* c */", $procedure->rows[0][2]);
    }
}
