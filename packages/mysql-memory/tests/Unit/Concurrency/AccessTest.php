<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use MySqlMemory\Concurrency\Access;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Access::class)]
#[Small]
final class AccessTest extends TestCase
{
    public function testCheckRefusesAWriteInAReadOnlyTransactionBeforeAnUnknownTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); START TRANSACTION READ ONLY');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);
        $this->expectExceptionMessage('Cannot execute statement in a READ ONLY transaction.');

        $session->query('INSERT INTO nosuch VALUES (1)');
    }

    public function testCheckLetsAReadOnlyTransactionWriteATemporaryTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TEMPORARY TABLE tt (a INT); SET TRANSACTION READ ONLY; START TRANSACTION; INSERT INTO tt VALUES (1); UPDATE tt SET a = 2');
        $result = $session->query('SELECT a FROM tt FOR UPDATE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testCheckRefusesATemporaryTableCreatedInAReadOnlyTransaction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('START TRANSACTION READ ONLY');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);

        $session->query('CREATE TEMPORARY TABLE tt (a INT)');
    }

    public function testCheckLetsAStatementThatCommitsEndAReadOnlyTransaction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('START TRANSACTION READ ONLY; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testCheckRefusesAStatementThatCommitsInAReadOnlySession(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); SET SESSION TRANSACTION READ ONLY');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);

        $session->query('CREATE TABLE IF NOT EXISTS t (a INT)');
    }

    public function testWritesLockedTellsLockTablesWithAWriteLock(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); SET SESSION TRANSACTION READ ONLY; LOCK TABLES t READ; UNLOCK TABLES');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);

        $session->query('LOCK TABLES t WRITE');
    }

    public function testWrittenAnswersTheTablesAStatementWrites(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $access = new Access();
        $insert = $session->analyze('INSERT INTO d.t VALUES (1)')->statement;
        $update = $session->analyze('UPDATE t JOIN u ON t.a = u.a SET t.a = 1')->statement;
        $select = $session->analyze('SELECT 1')->statement;

        self::assertSame([['t'], [null], []], [array_map(static fn ($name): ?string => $name?->name->value, $access->written($insert)), $access->written($update), $access->written($select)]);
    }
}
