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

    public function testActionSetsTheCharacterSetOfTheClientAndTheResults(): void
    {
        $session = (new Instance())->connect();

        $session->query('SET CHARACTER SET latin1');
        $result = $session->query('SELECT @@character_set_client, @@character_set_results')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['latin1', 'latin1']], $result->rows);
    }

    public function testActionSetsTheDefaultNames(): void
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
}
