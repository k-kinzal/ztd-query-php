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
    /**
     * @return iterable<string, array{\SqlSemantics\Platform\MySql\Statement\Statement}>
     */
    public static function providerPreparedCommands(): iterable
    {
        $name = new \SqlSemantics\Statement\Identifier\Name('p');
        $xid = new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('x'));
        yield 'xastart' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart($xid)];
        yield 'xaend' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd($xid)];
        yield 'xaprepare' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare($xid)];
        yield 'xacommit' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit($xid)];
        yield 'xarollback' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback($xid)];
        yield 'xarecover' => [new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover()];
        yield 'prepare' => [new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare($name, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('invalid dynamic SQL'))];
        yield 'execute' => [new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute($name)];
        yield 'deallocate' => [new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate($name)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerPreparedCommands')]
    public function testProtocolRefusesNestedPreparationWithoutLookingUpRuntimeState(\SqlSemantics\Platform\MySql\Statement\Statement $statement): void
    {
        $this->expectExceptionCode(1295);
        $this->expectExceptionMessage('This command is not supported in the prepared statement protocol yet');
        (new Preparation())->protocol($statement);
    }

    public function testJsonNullsChecksTheCurrentLegacyDocumentWithoutItsRequestText(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET @document='broken'");
        $operation = $session->analyze("SELECT JSON_EXTRACT(@document, '$') IS NULL");
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(3141);

        (new Preparation())->jsonNulls($operation, $session);
    }

    public function testCheckRefusesAConstructedPurgeBeforeEvaluatingItsCutoff(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated SELECT 1';
        $statement = new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral());
        $operation = new \SqlSemantics\Statement\Operation($session->semantics()->context(session: $session->resolution()), $statement);
        $this->expectExceptionCode(1295);

        (new Preparation())->check($operation, $session);
    }

    public function testCheckRefusesConstructedDiagnosticsWithoutReadingTheRequestLabel(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'opaque non-SQL label';
        $statement = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics(new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics([new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('n')), \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName::Number)]));
        $operation = new \SqlSemantics\Statement\Operation($session->semantics()->context(session: $session->resolution()), $statement);
        $this->expectExceptionCode(1295);
        $this->expectExceptionMessage('This command is not supported in the prepared statement protocol yet');

        (new Preparation())->check($operation, $session);
    }

    public function testResourcesRefusesDirectDefinitionsWithoutReadingRequestText(): void
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName(new \SqlSemantics\Statement\Identifier\Name('g'));
        $this->expectExceptionCode(1295);
        (new Preparation())->resources(new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup($name));
    }

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
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1295);
        (new Preparation())->definitions($operation, $session);
    }

    public function testProtocolRefusesConcreteRoleCreationBeforeAccountMutation(): void
    {
        $statement = new \SqlSemantics\Platform\MySql\Statement\Account\CreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
        $this->expectExceptionCode(1295);
        (new Preparation())->protocol($statement);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['DROP ROLE r'])]
    public function testDefinitionsRefusesRoleDefinitionPreparationBeforeAccountMutation(string $sql): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze($sql, true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1295);
        (new Preparation())->definitions($operation, $session);
    }

    public function testDefinitionsKeepsEventPreparationSeparateFromScheduleEvaluation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1295);

        (new Preparation())->definitions($operation, $session);
    }

    public function testDefinitionsRefusesEventScheduleParametersInModernPreparation(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect();
        $session->query('USE d');
        $operation = $session->analyze('CREATE EVENT e ON SCHEDULE AT ? DO SELECT ?', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(6413);
        $this->expectExceptionMessage('Dynamic parameters can only be used in DML statements.');

        (new Preparation())->definitions($operation, $session);
    }

    public function testCheckRequiresADatabaseBeforeRefusingPreparedRoutineDeletion(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('DROP PROCEDURE IF EXISTS p', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1046);

        (new Preparation())->check($operation, $session);
    }

    public function testProgramRefusesPreparationBeforeLookingUpTheRoutineDatabase(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('CREATE FUNCTION missing.f() RETURNS INT RETURN 1', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
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

    public function testHintsRestoresTypingHintsAfterSuccessfulPreparation(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT /*+ SET_VAR(div_precision_increment=10) */ ?/3', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);

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
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1295);
        (new Preparation())->check($operation, $session);
    }

    public function testCheckResolvesLegacyIndexDatabaseBeforeParserPlugin(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $operation = $session->analyze('CREATE FULLTEXT INDEX i ON t(a) WITH PARSER missing', true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1046);
        (new Preparation())->check($operation, $session);
    }

    public function testCheckResolvesLegacyLoadDatabaseBeforeCharset(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $operation = $session->analyze("LOAD DATA INFILE 'f' INTO TABLE t CHARSET missing SET a=1", true);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        $this->expectExceptionCode(1046);
        (new Preparation())->check($operation, $session);
    }

    public function testCheckRejectsPreparingPurgeLogs(): void
    {
        $session = (new Instance())->connect();
        $statement = new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter());
        $parameters = [['2024-01-01 00:00:00', \MySqlMemory\Typing\Domain::string(19, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]];
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement, $parameters);
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1295);
        (new Preparation())->check($operation, $session);
    }

    public function testCheckRefusesHandlerParametersBeforeNativeExecution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT PRIMARY KEY); INSERT INTO t VALUES(1),(2),(3),(4),(5); HANDLER t OPEN AS h');
        $where = new \SqlSemantics\Platform\MySql\Statement\Expression\Comparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Less, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter());
        $limit = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(), new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(), \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Keyword);
        $node = new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler(new \SqlSemantics\Statement\Identifier\Name('h')), new \SqlSemantics\Statement\Identifier\Name('PRIMARY'), \SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison::GreaterOrEqual, [new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter()], $where, $limit);
        $domain = \MySqlMemory\Typing\Domain::integer(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(1295);
        $this->expectExceptionMessage('This command is not supported in the prepared statement protocol yet');
        $statement1 = (new \Tests\Generator\Embedding())->statement($node);
        $parameters1 = [[2, $domain], [5, $domain], [1, $domain], [1, $domain]];
        $session->activity->begin();
        \MySqlMemory\Session\State\StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement1, $parameters1, new \SqlSemantics\Contract\LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new \Tests\Generator\Bindings())->parameters($statement1)));
        (new Preparation())->check($operation1, $session);
    }
}
