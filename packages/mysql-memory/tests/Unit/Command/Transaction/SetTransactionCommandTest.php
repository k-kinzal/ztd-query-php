<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Transaction;

use MySqlMemory\Command\Transaction\SetTransactionCommand;
use MySqlMemory\Concurrency\Isolation;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(SetTransactionCommand::class)]
#[Small]
final class SetTransactionCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new SetTransactionCommand())->clearsDiagnostics());
    }

    public function testExecuteGivesTheNextTransactionItsCharacteristicsWithoutChangingTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
        $result = $session->query('SELECT @@transaction_isolation, @@transaction_read_only')[0];
        $next = [$session->transaction->nextIsolation, $session->transaction->nextReadOnly];
        $session->query('BEGIN');

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['REPEATABLE-READ', '0']], [Isolation::ReadCommitted, true], Isolation::ReadCommitted, true, [null, null]], [$result->rows, $next, $session->transaction->isolation, $session->transaction->readOnly, [$session->transaction->nextIsolation, $session->transaction->nextReadOnly]]);
    }

    public function testExecuteSetsTheSessionAndGlobalValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE; SET GLOBAL TRANSACTION READ ONLY');
        $result = $session->query('SELECT @@session.transaction_isolation, @@global.transaction_read_only, @@session.transaction_read_only')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['SERIALIZABLE', '1', '0']], $result->rows);
    }

    public function testNextRefusesCharacteristicsWhileATransactionIsActive(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1568);
        $this->expectExceptionMessage("Transaction characteristics can't be changed while a transaction is in progress");

        (new SetTransactionCommand())->next($session, 'transaction_read_only', 'ON');
    }

    public function testStoreSetsBothNamesMySql57Has(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        (new SetTransactionCommand())->store($session, VariableScope::Session, 'transaction_isolation', 'READ-UNCOMMITTED');
        $result = $session->query('SELECT @@tx_isolation, @@transaction_isolation')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['READ-UNCOMMITTED', 'READ-UNCOMMITTED']], $result->rows);
    }
}
