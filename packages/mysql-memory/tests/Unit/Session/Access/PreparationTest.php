<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preparation::class)]
#[Small]
final class PreparationTest extends TestCase
{
    public function testReadKeepsTheProgramArrayErrorBeforeMalformedPreparedSyntax(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d');
        $this->expectExceptionCode(1221);
        $this->expectExceptionMessage('Incorrect usage of CAST( .. AS .. ARRAY) and stored routines');

        (new Preparation())->read('CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY),', $session);
    }

    public function testReadRefusesMultipleStatementsAtTheSecondStatement(): void
    {
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage("near 'SELECT 2' at line 2");

        (new Preparation())->read("SELECT 1;\nSELECT 2", (new Instance())->connect());
    }

    public function testDefinitionsKeepsEventPreparationSeparateFromScheduleEvaluation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1', true);
        $this->expectExceptionCode(1295);

        (new Preparation())->definitions($operation, $session);
    }

    public function testCheckRequiresADatabaseBeforeRefusingPreparedRoutineDeletion(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('DROP PROCEDURE IF EXISTS p', true);
        $this->expectExceptionCode(1046);

        (new Preparation())->check($operation, $session);
    }

    public function testProgramRefusesPreparationBeforeLookingUpTheRoutineDatabase(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('CREATE FUNCTION missing.f() RETURNS INT RETURN 1', true);
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1295);
        (new Preparation())->program($operation, $session);
    }

    public function testCheckRecordsResolutionBeforeResourceGroupConditions(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d');
        $session->run("PREPARE p FROM 'SELECT /*+ RESOURCE_GROUP(missing) */ ? FROM missing'");

        self::assertSame([['Error', 1146, "Table 'd.missing' doesn't exist"], ['Warning', 3651, "Resource Group 'missing' does not exist."]], $session->diagnostics->conditions);
    }

    public function testCheckRestoresTypingHintsAfterSuccessfulPreparation(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT /*+ SET_VAR(div_precision_increment=10) */ ?/3', true);

        (new Preparation())->check($operation, $session);

        self::assertSame(4, $session->variables->read('div_precision_increment'));
        self::assertSame([], $session->diagnostics->conditions);
    }
}
