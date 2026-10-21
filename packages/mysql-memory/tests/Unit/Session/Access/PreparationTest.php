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
    public function testStorageRefusesTheConstructedCommandWithoutReadingInput(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace(new \SqlSemantics\Statement\Identifier\Name('missing'));
        $this->expectExceptionCode(1295);
        (new Preparation())->storage($node, (new Instance())->connect());
    }

    public function testStorageResolvesTheLegacyEngineBeforeTheProtocolRestriction(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace(new \SqlSemantics\Statement\Identifier\Name('missing'), [new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption(new \SqlSemantics\Statement\Identifier\Name('absent_engine'))]);
        $this->expectExceptionCode(1286);
        (new Preparation())->storage($node, (new Instance('5.7.44'))->connect());
    }

    public function testAlterParametersUsesTheRetainedMarkerWithoutReadingTheLabel(): void
    {
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex(new \SqlSemantics\Statement\Identifier\Name('k'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]);
        $session = (new Instance('9.1.0'))->connect();
        $session->text = 'opaque request label';
        $this->expectExceptionMessage('Dynamic parameters can only be used in DML statements.');
        (new Preparation())->alterParameters($definition, $session);
    }

    public function testAlterParametersKeepsEarlierReleasesOpenForExecution(): void
    {
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex(new \SqlSemantics\Statement\Identifier\Name('k'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]);
        $session = (new Instance('8.4.7'))->connect();
        (new Preparation())->alterParameters($definition, $session);
        self::assertSame(0, $session->diagnostics->raised());
    }

    public function testCheckRefusesAnEmptySemanticScriptWithoutReadingItsRequestLabel(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated SELECT 1';
        $operation = new \SqlSemantics\Statement\Operation($session->semantics()->context(session: $session->resolution()), new \SqlSemantics\Statement\Script([]));
        $this->expectExceptionCode(1295);
        (new Preparation())->check($operation, $session);
    }

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

    #[\PHPUnit\Framework\Attributes\TestWith(["CREATE SPATIAL REFERENCE SYSTEM 500001 NAME 'local' DEFINITION 'invalid'"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP SPATIAL REFERENCE SYSTEM 4326'])]
    public function testDefinitionsRefusesSpatialDdlBeforeCatalogMutation(string $sql): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql, true);
        $this->expectExceptionCode(1295);
        (new Preparation())->definitions($operation, $session);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE ROLE r'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP ROLE r'])]
    public function testDefinitionsRefusesRoleDefinitionPreparationBeforeAccountMutation(string $sql): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql, true);
        $this->expectExceptionCode(1295);
        (new Preparation())->definitions($operation, $session);
    }

    public function testDefinitionsKeepsEventPreparationSeparateFromScheduleEvaluation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1', true);
        $this->expectExceptionCode(1295);

        (new Preparation())->definitions($operation, $session);
    }

    public function testDefinitionsRefusesEventScheduleParametersInModernPreparation(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect();
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT ? DO SELECT ?', true);
        $this->expectExceptionCode(6413);
        $this->expectExceptionMessage('Dynamic parameters can only be used in DML statements.');

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
    #[\PHPUnit\Framework\Attributes\TestWith(['HANDLER missing OPEN'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['HANDLER missing READ FIRST'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['HANDLER missing CLOSE'])]
    public function testCheckRefusesHandlerPreparationBeforeOpeningItsTarget(string $sql): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql, true);
        $this->expectExceptionCode(1295);
        (new Preparation())->check($operation, $session);
    }
    public function testCheckResolvesLegacyIndexDatabaseBeforeParserPlugin(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $operation = $session->analyze('CREATE FULLTEXT INDEX i ON t(a) WITH PARSER missing', true);
        $this->expectExceptionCode(1046);
        (new Preparation())->check($operation, $session);
    }
}
