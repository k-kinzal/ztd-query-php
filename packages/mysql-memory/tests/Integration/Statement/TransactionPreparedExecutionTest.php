<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use mysqli;
use mysqli_sql_exception;
use MySqlMemory\Instance;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;

#[CoversNothing]
#[Large]
final class TransactionPreparedExecutionTest extends TestCase
{
    public function testBinaryPreparationRetainsConcreteTransactionCommands(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $begin = $pdo->prepare('BEGIN WORK');
            self::assertNotFalse($begin);
            self::assertFalse($pdo->inTransaction());
            self::assertTrue($begin->execute());
            self::assertTrue($pdo->inTransaction());
            $begin->closeCursor();
            $commit = $pdo->prepare('COMMIT AND NO CHAIN NO RELEASE');
            self::assertNotFalse($commit);
            self::assertTrue($pdo->inTransaction());
            self::assertTrue($commit->execute());
            self::assertFalse($pdo->inTransaction());
            $commit->closeCursor();
            $mysqli = new mysqli($server->host, 'root', '', '', $server->port);
            $this->expectException(mysqli_sql_exception::class);
            $this->expectExceptionCode(1295);
            $this->expectExceptionMessage('This command is not supported in the prepared statement protocol yet');
            $mysqli->prepare('XA RECOVER');
        } finally {
            $begin = null;
            $commit = null;
            $pdo = null;
            $mysqli = null;
            $server->stop();
        }
    }

    public function testSqlPreparationAndStoredProgramsUseConcreteTransactionCommands(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute("PREPARE tx FROM 'BEGIN WORK'");
        self::assertFalse($session->transaction->active());
        $prepared = $session->preparation->named['tx'][3]->definition;
        self::assertNull($session->preparation->named['tx'][3]->input);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $prepared);
        self::assertInstanceOf(Begin::class, $prepared->statement);
        self::assertSame($session->resolution()->connection, $prepared->literals->connection);
        $session->execute('EXECUTE tx');
        self::assertTrue($session->transaction->active());
        $session->execute('ROLLBACK');
        $session->execute('CREATE PROCEDURE p() BEGIN START TRANSACTION; SAVEPOINT s; ROLLBACK TO s; RELEASE SAVEPOINT s; COMMIT; END');
        $session->execute('CALL p()');
        self::assertFalse($session->transaction->active());
        self::assertSame([], $session->transaction->savepoints->list);
        self::assertSame(2, $session->instance->registry->status->read('Com_begin', $session->id));
        self::assertSame([], $session->transaction->statements->running);
    }
}
