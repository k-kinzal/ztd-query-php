<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\KillCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(KillCommand::class)]
#[Medium]
final class KillCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDependencies(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7'] as $version) {
            foreach (['KILL (SELECT 0 FROM absent)', 'KILL absent_function()'] as $sql) {
                yield $version . ' ' . $sql => [$version, $sql];
            }
        }
    }

    #[DataProvider('providerDependencies')]
    public function testPrepareDefersTableAndRoutineDependenciesUntilExecute(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $reply = $session->query("PREPARE s FROM '" . $sql . "'")[0];
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $session->instance->registry->status->read('Com_kill', $session->id));
        $replies = $session->run('EXECUTE s');

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame(1235, $replies[0]->getCode());
        self::assertSame([['Error', 1235, "This version of MySQL doesn't yet support 'Usage of subqueries or stored function calls as part of this statement'"]], $session->diagnostics->conditions);
        self::assertSame(1, $session->instance->registry->status->read('Com_kill', $session->id));
    }

    public function testPrepareRetainsTheRowFailureUntilExecute(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('KILL ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $statement);
        (new KillCommand())->prepare($statement, $session);
        self::assertSame([], $session->diagnostics->conditions);
        $this->expectExceptionCode(1241);
        (new \MySqlMemory\Session\Execution($session))->statement($statement);
    }

    public function testPrepareRefusesAnUnknownSystemVariableBeforeExecution(): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run('KILL ROW(@@missing,2)')[0];
        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1193, $reply->getCode());
        self::assertSame([1193], array_column($session->diagnostics->conditions, 1));
    }

    public function testProcessReturnsAScalarWithoutEvaluatingItsIdentifier(): void
    {
        $session = (new Instance())->connect();
        $kill = $session->analyze('KILL 0');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $kill);
        self::assertSame($kill->process, (new KillCommand())->process($kill, $session));
    }

    public function testProcessRetainsTheFailureFollowingAnUnknownColumn(): void
    {
        $session = (new Instance())->connect();
        $kill = $session->analyze('KILL ROW(missing,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::class, $kill);
        try {
            (new KillCommand())->process($kill, $session);
            self::fail('An unknown process dependency must fail before execution.');
        } catch (SqlError $error) {
            self::assertSame(1054, $error->getCode());
            self::assertSame([[1204, 'You may only use constant expressions with SET']], $error->following);
        }
    }

    public function testExecuteDoesNotFindAClosedSessionThatIsStillReferenced(): void
    {
        $instance = new Instance();
        $target = $instance->connect();
        $target->close();
        $killer = $instance->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1094);

        $killer->query('KILL ' . $target->id);
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new KillCommand())->clearsDiagnostics());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerIdentifiers(): iterable
    {
        yield 'zero' => ['0', '0'];
        yield 'null' => ['NULL', '0'];
        yield 'negative' => ['-1', '4294967295'];
        yield 'rounded decimal' => ['8.5', '9'];
        yield 'scalar subquery' => ['(SELECT 0)', '0'];
        yield 'unsigned' => ['18446744073709551615', '4294967295'];
    }

    #[DataProvider('providerIdentifiers')]
    public function testExecuteConvertsTheIdentifier(string $operand, string $identifier): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1094);
        $this->expectExceptionMessage('Unknown thread id: ' . $identifier);

        $session->query('KILL ' . $operand);
    }

    public function testIdentifierPreservesTheMySql56UnsignedWidth(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $this->expectExceptionCode(1094);
        $this->expectExceptionMessage('Unknown thread id: 18446744073709551615');

        $session->query('KILL -1');
    }

    public function testIdentifierKeepsTheLegacyQuietStringConversion(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run('KILL USER()');

        self::assertSame([['Error', 1094, 'Unknown thread id: 0']], $session->diagnostics->conditions);
    }

    public function testIdentifierRecordsTheUnknownZeroThreadAfterMySql56EvaluationFailure(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->run('KILL (SELECT 1 UNION SELECT 2)');

        self::assertSame([['Error', 1242, 'Subquery returns more than 1 row'], ['Error', 1094, 'Unknown thread id: 0']], $session->diagnostics->conditions);
    }

    public function testExecuteKeepsPriorConditionsUntilItRaisesANewOneInMySql56(): void
    {
        $instance = new Instance('5.6.51');
        $target = $instance->connect();
        $session = $instance->connect();
        $session->run('KILL 0');
        $session->query('KILL ' . $target->id);
        self::assertTrue($target->released);
        self::assertSame([['Error', 1094, 'Unknown thread id: 0']], $session->diagnostics->conditions);
        $session->run('KILL ' . $target->id);
        self::assertSame([['Error', 1094, 'Unknown thread id: ' . $target->id]], $session->diagnostics->conditions);
    }

    public function testExecuteLeavesAnIdleConnectionOpenForQueryOnly(): void
    {
        $instance = new Instance();
        $target = $instance->connect();
        $killer = $instance->connect();
        $reply = $killer->query('KILL QUERY ' . $target->id)[0];
        $result = $target->query('SELECT 1')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertFalse($target->released);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testExecuteRollsBackAConnectionItEnds(): void
    {
        $instance = new Instance();
        $target = $instance->connect();
        $target->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT); BEGIN; INSERT INTO t VALUES (1)');
        $killer = $instance->connect('root', 'localhost', 'd');
        $killer->query('KILL CONNECTION ' . $target->id);
        $result = $killer->query('SELECT * FROM t')[0];

        self::assertTrue($target->released);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testPreparationRetainsTheResolutionErrorAndTheFollowingCondition(): void
    {
        $session = (new Instance())->connect();
        $replies = $session->run('KILL missing');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame(1054, $replies[0]->getCode());
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1054', "Unknown column 'missing' in 'field list'"], ['Error', '1204', 'You may only use constant expressions with SET']], $warnings->rows);
    }
}
