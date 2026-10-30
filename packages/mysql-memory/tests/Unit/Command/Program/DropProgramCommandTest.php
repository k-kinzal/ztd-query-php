<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\DropProgramCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DropProgramCommand::class)]
#[Small]
final class DropProgramCommandTest extends TestCase
{
    #[DataProvider('providerLockedEvents')]
    public function testExecuteChecksEventLocksBeforeExistence(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SET @x=1; LOCK TABLE t WRITE; ROLLBACK');
        $this->expectExceptionCode(1192);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLockedEvents(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['DROP EVENT e', 'DROP EVENT absent', 'DROP EVENT IF EXISTS absent', 'DROP EVENT missing_db.e'] as $sql) {
                yield $version . ':' . $sql => [$version, $sql];
            }
        }
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DropProgramCommand())->clearsDiagnostics());
    }

    public function testExecuteDropsAProcedure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() SELECT 1');

        $session->query('DROP PROCEDURE P');

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame([], $schema->procedures);
    }

    public function testExecuteNotesAMissingRoutineWithIfExists(): void
    {
        $session = (new Instance())->connect();

        $session->query('DROP FUNCTION IF EXISTS nope.f');

        self::assertSame([['Note', 1305, 'FUNCTION nope.f does not exist']], $session->diagnostics->conditions);
    }

    public function testExecuteNamesALoadableFunctionWithoutADatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('FUNCTION (UDF) p does not exist');

        $session->query('DROP FUNCTION p');
    }

    public function testExecuteRefusesAMissingTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1360);
        $this->expectExceptionMessage('Trigger does not exist');

        $session->query('DROP TRIGGER SUPER');
    }

    public function testExecuteNotesATriggerOfAMissingDatabaseWithTheFormatUnfilled(): void
    {
        $session = (new Instance())->connect();

        $session->query('DROP TRIGGER IF EXISTS `name`.`name`');

        self::assertSame([['Note', 1049, "Unknown database '%-.192s'"]], $session->diagnostics->conditions);
    }

    public function testExecuteRefusesAMissingEventButNotesItAsARoutine(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query('DROP EVENT IF EXISTS e');
        $notes = $session->diagnostics->conditions;
        $error = $session->run('DROP EVENT e')[0];
        self::assertInstanceOf(SqlError::class, $error);

        self::assertSame([['Note', 1305, 'Event e does not exist']], $notes);
        self::assertSame([1539, "Unknown event 'e'"], [$error->getCode(), $error->getMessage()]);
    }

    public function testDropAnswersTheErrorOfAMissingProcedure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $missing = (new DropProgramCommand())->drop(new DropProgram(ProgramKind::Procedure, new QualifiedName(new Name('p'))), $session);

        self::assertNotNull($missing);
        self::assertSame('PROCEDURE d.p does not exist', $missing[0]->getMessage());
    }

    public function testRoutineDropsAFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        $missing = (new DropProgramCommand())->routine(new DropProgram(ProgramKind::Function, new QualifiedName(new Name('F'))), $session, $schema, 'd');

        self::assertSame([null, []], [$missing, $schema->functions]);
    }

    public function testTriggerAnswersTheUnfilledNoteOfAMissingDatabase(): void
    {
        $missing = (new DropProgramCommand())->trigger(null, 'x', 't');

        self::assertNotNull($missing);
        self::assertSame(["Unknown database 'x'", "Unknown database '%-.192s'"], [$missing[0]->getMessage(), $missing[1]->getMessage()]);
    }

    public function testEventAnswersAnEventErrorAndARoutineNote(): void
    {
        $missing = (new DropProgramCommand())->event(null, 'E');

        self::assertNotNull($missing);
        self::assertSame([[1539, "Unknown event 'E'"], [1305, 'Event E does not exist']], [[$missing[0]->getCode(), $missing[0]->getMessage()], [$missing[1]->getCode(), $missing[1]->getMessage()]]);
    }

    public function testTriggerFindsNoTriggerInADatabaseThatDoesNotExistInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1360);

        $session->query('DROP TRIGGER nodb.x');
    }
}
