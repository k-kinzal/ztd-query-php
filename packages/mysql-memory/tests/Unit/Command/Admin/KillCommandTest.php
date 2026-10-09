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
