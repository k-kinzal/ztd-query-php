<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class InsertPreparedExecutionTest extends TestCase
{
    public function testNativePreparationExecutesLiteralValuesAndEmptyRows(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec('CREATE DATABASE pbt');
            $pdo->exec('USE pbt');
            $pdo->exec('CREATE TABLE t (a INT)');
            $statement = $pdo->prepare("INSERT t VALUE ('42')");
            self::assertNotFalse($statement);
            self::assertTrue($statement->execute());
            self::assertSame(1, $statement->rowCount());
            $statement->closeCursor();
            $empty = $pdo->prepare('INSERT t VALUES (), ()');
            self::assertNotFalse($empty);
            self::assertTrue($empty->execute());
            self::assertSame(2, $empty->rowCount());
            $empty->closeCursor();
            $result = $pdo->query('SELECT a FROM t ORDER BY a');
            self::assertNotFalse($result);
            self::assertSame([[null], [null], [42]], $result->fetchAll(PDO::FETCH_NUM));
            $pdo = null;
        } finally {
            $server->stop();
        }
    }

    public function testSqlPrepareRetainsItsConcreteInsertThroughExecution(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT)');
        $session->execute("PREPARE statement_name FROM 'INSERT t VALUE (\'42\')'");
        $reply = $session->execute('EXECUTE statement_name');
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        $table = $session->instance->dictionary->table('pbt', 't');
        self::assertNotNull($table);
        self::assertSame([[42]], array_values($table->data->rows));
    }
}
