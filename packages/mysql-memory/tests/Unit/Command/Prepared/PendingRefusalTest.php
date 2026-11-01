<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingRefusal;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;

#[CoversClass(PendingRefusal::class)]
#[Medium]
final class PendingRefusalTest extends TestCase
{
    public function testParametersHasNoMarkers(): void
    {
        $sql = 'REVOKE ALL ON PROCEDURE * FROM u';
        try {
            (new Instance())->connect()->semantics()->analyze($sql);
            self::fail('ALL at the database level of a routine kind is refused.');
        } catch (RejectedInput $failure) {
            self::assertSame([], (new PendingRefusal($sql, $failure))->parameters()->types);
        }
    }

    public function testExecuteCommitsTheOpenTransactionAndRaisesTheRetainedRefusal(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('START TRANSACTION');
        $session->query('INSERT INTO t VALUES (7)');
        $sql = 'GRANT SUPER ON d.t TO u@host, v@other';
        try {
            $session->semantics()->analyze($sql);
            self::fail('SUPER is a global privilege.');
        } catch (RejectedInput $failure) {
            $pending = new PendingRefusal($sql, $failure);
        }
        try {
            $pending->execute($session, 'EXECUTE s', []);
            self::fail('The privilege level is refused when the statement executes.');
        } catch (SqlError $error) {
            self::assertSame(1144, $error->getCode());
        }
        self::assertSame([1287, 1285, 1285, 1144], array_column($session->diagnostics->conditions, 1));
        $session->query('ROLLBACK');
        $result = $session->query('SELECT a FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
    }
}
