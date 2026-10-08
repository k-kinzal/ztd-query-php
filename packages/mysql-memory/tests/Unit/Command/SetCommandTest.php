<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\SetCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(SetCommand::class)]
#[Small]
final class SetCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new SetCommand())->clearsDiagnostics());
    }

    public function testExecuteAssignsUserVariables(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SET @s = 'abc', @n = 1.5, @i = 3")[0];
        $result = $session->query('SELECT @s, @n, @i')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 0], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', '1.5', '3']], $result->rows);
    }

    public function testExecuteComputesEveryValueBeforeAssigningAny(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET @a = 1, @b = @a + 1');
        $result = $session->query('SELECT @a, @b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null]], $result->rows);
    }

    public function testExecuteLeavesEveryVariableAsItWasWhenAnAssignmentIsRefused(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @a = 1');

        $error = $session->run("SET @a = 5, sql_mode = 'NOPE'")[0];
        $result = $session->query('SELECT @a, @@sql_mode')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1231, "Variable 'sql_mode' can't be set to the value of 'NOPE'"], [$error->getCode(), $error->getMessage()]);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION']], $result->rows);
    }

    public function testExecuteAssignsASystemVariableInTheScopeItNames(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET SESSION div_precision_increment = 7');
        $result = $session->query('SELECT @@global.div_precision_increment, @@session.div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '7']], $result->rows);
    }

    public function testValueTakesABareWordAndDefault(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET autocommit = OFF');
        $off = $session->query('SELECT @@autocommit')[0];
        $session->query('SET autocommit = DEFAULT');
        $default = $session->query('SELECT @@autocommit')[0];

        self::assertInstanceOf(ResultSet::class, $off);
        self::assertSame([['0']], $off->rows);
        self::assertInstanceOf(ResultSet::class, $default);
        self::assertSame([['1']], $default->rows);
    }

    public function testActionSetsNamesForTheConnection(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET NAMES latin1');
        $result = $session->query('SELECT @@character_set_client, @@character_set_results, @@character_set_connection, @@collation_connection')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['latin1', 'latin1', 'latin1', 'latin1_swedish_ci']], $result->rows);
    }

    public function testActionSetsNamesWithTheCollationItNames(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET NAMES utf8mb4 COLLATE utf8mb4_bin');
        $result = $session->query('SELECT @@character_set_connection, @@collation_connection')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['utf8mb4', 'utf8mb4_bin']], $result->rows);
    }

    public function testCharsetSetsTheCharacterSetOfTheClientAndTheResults(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET CHARACTER SET latin1');
        $result = $session->query('SELECT @@character_set_client, @@character_set_results')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['latin1', 'latin1']], $result->rows);
    }

    public function testCharsetSetsTheDefaultNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET NAMES latin1');

        $session->query('SET NAMES DEFAULT');
        $result = $session->query('SELECT @@character_set_client, @@character_set_results, @@character_set_connection, @@collation_connection')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['utf8mb4', 'utf8mb4', 'utf8mb4', 'utf8mb4_0900_ai_ci']], $result->rows);
    }

    public function testValueComputesAnExpressionForASystemVariable(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET div_precision_increment = 2 + 3');
        $result = $session->query('SELECT @@div_precision_increment')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5']], $result->rows);
    }

    public function testScopeWritesTheGlobalValueForGlobalAndPersist(): void
    {
        $command = new SetCommand();

        self::assertSame(
            [Scope::Global, Scope::Global, Scope::Global, Scope::Session, Scope::Session],
            [$command->scope(VariableScope::Global), $command->scope(VariableScope::Persist), $command->scope(VariableScope::PersistOnly), $command->scope(VariableScope::Session), $command->scope(null)],
        );
    }

    public function testVariableAssignsAVariableOfTheProgramAtOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE x, y INT DEFAULT 7; SET x = 1, @u = x + y; SET @v = x, x = 3, y = x; SELECT @u, @v, x, y; END');

        $result1 = $session->query('CALL p()')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['8', '1', '3', '3']], $result1->rows);
    }

    public function testVariableRefusesAColumnOfTheNewRowOfAnAfterTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE PROCEDURE p() SELECT 1');
        $program = new \MySqlMemory\Program\Activation('TRIGGER', 'd.t', true, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'));
        $program->scope[] = new \MySqlMemory\Program\Row(new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), [new \MySqlMemory\Program\Variable('a', \MySqlMemory\Typing\Domain::integer())], 'NEW');
        $session->program = $program;
        $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TRIGGER x AFTER INSERT ON t FOR EACH ROW SET NEW.a = 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger::class, $trigger);
        $statement = $trigger->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $statement);
        $item = $statement->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment::class, $item);

        $this->expectExceptionCode(1362);
        $this->expectExceptionMessage('Updating of NEW row is not allowed in after trigger');

        (new SetCommand())->variable($item, $session);
    }

    public function testLocalAssignsTheVariableABareNameNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 4; DECLARE y INT; SET y = x; SELECT y; END');

        $result = $session->query('CALL p()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4']], $result->rows);
    }
    public function testActionGivesTheNextTransactionTheIsolationLevelAnUnscopedVariableNames(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @@transaction_isolation = 'READ-COMMITTED'");
        $result = $session->query('SELECT @@transaction_isolation')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['REPEATABLE-READ']], \MySqlMemory\Concurrency\Isolation::ReadCommitted], [$result->rows, $session->transaction->nextIsolation]);
    }

    public function testActionRefusesTheNextTransactionAccessModeWhileATransactionIsActive(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SET @@session.transaction_read_only = 1');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1568);

        $session->query('SET @@transaction_read_only = 1');
    }
}
