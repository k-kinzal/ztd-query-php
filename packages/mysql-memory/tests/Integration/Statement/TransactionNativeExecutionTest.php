<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Container\Endpoint;
use Container\MySqlRelease;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TransactionNativeExecutionTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testTransactionFamiliesAgreeWithNativeRepliesAndStoredRows(string $version): void
    {
        $reply = static function (PDO $connection, string $sql): array {
            $statement = $connection->query($sql);
            self::assertInstanceOf(PDOStatement::class, $statement);
            $rows = $statement->columnCount() === 0 ? [] : $statement->fetchAll(PDO::FETCH_NUM);
            $affected = $statement->rowCount();
            $statement->closeCursor();
            $warnings = $connection->query('SHOW WARNINGS');
            self::assertInstanceOf(PDOStatement::class, $warnings);
            return [$affected, $rows, $warnings->fetchAll(PDO::FETCH_NUM)];
        };
        $native = Testcontainers::run((new (MySqlRelease::container($version))())->withReuseMode(ReuseMode::ADD()));
        $memory = null;
        try {
            $memory = MemoryDatabase::start($version);
            $endpoint = $native->getData(Endpoint::class);
            $real = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $emulated = new PDO($memory->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $real->exec('CREATE DATABASE transaction_fixture');
            $real->exec('USE transaction_fixture');
            $real->exec('CREATE TABLE items (value INT) ENGINE=InnoDB');
            $real->exec('INSERT INTO items VALUES (0)');
            $emulated->exec('CREATE DATABASE transaction_fixture');
            $emulated->exec('USE transaction_fixture');
            $emulated->exec('CREATE TABLE items (value INT) ENGINE=InnoDB');
            $emulated->exec('INSERT INTO items VALUES (0)');
            self::assertSame($reply($real, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE'), $reply($emulated, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE'), $version . ': ' . 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'BEGIN WORK'), $reply($emulated, 'BEGIN WORK'), $version . ': ' . 'BEGIN WORK');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'UPDATE items SET value = 1'), $reply($emulated, 'UPDATE items SET value = 1'), $version . ': ' . 'UPDATE items SET value = 1');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'SAVEPOINT FULL'), $reply($emulated, 'SAVEPOINT FULL'), $version . ': ' . 'SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'UPDATE items SET value = 2'), $reply($emulated, 'UPDATE items SET value = 2'), $version . ': ' . 'UPDATE items SET value = 2');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'ROLLBACK WORK TO SAVEPOINT FULL'), $reply($emulated, 'ROLLBACK WORK TO SAVEPOINT FULL'), $version . ': ' . 'ROLLBACK WORK TO SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'SELECT value FROM items'), $reply($emulated, 'SELECT value FROM items'), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'RELEASE SAVEPOINT FULL'), $reply($emulated, 'RELEASE SAVEPOINT FULL'), $version . ': ' . 'RELEASE SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'COMMIT AND CHAIN NO RELEASE'), $reply($emulated, 'COMMIT AND CHAIN NO RELEASE'), $version . ': ' . 'COMMIT AND CHAIN NO RELEASE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'SELECT value FROM items'), $reply($emulated, 'SELECT value FROM items'), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'ROLLBACK WORK AND NO CHAIN NO RELEASE'), $reply($emulated, 'ROLLBACK WORK AND NO CHAIN NO RELEASE'), $version . ': ' . 'ROLLBACK WORK AND NO CHAIN NO RELEASE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT'), $reply($emulated, 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT'), $version . ': ' . 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'ROLLBACK'), $reply($emulated, 'ROLLBACK'), $version . ': ' . 'ROLLBACK');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA START 'g', 'b', 7"), $reply($emulated, "XA START 'g', 'b', 7"), $version . ': ' . "XA START 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'UPDATE items SET value = 3'), $reply($emulated, 'UPDATE items SET value = 3'), $version . ': ' . 'UPDATE items SET value = 3');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA END 'g', 'b', 7"), $reply($emulated, "XA END 'g', 'b', 7"), $version . ': ' . "XA END 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA PREPARE 'g', 'b', 7"), $reply($emulated, "XA PREPARE 'g', 'b', 7"), $version . ': ' . "XA PREPARE 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'XA RECOVER'), $reply($emulated, 'XA RECOVER'), $version . ': ' . 'XA RECOVER');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA COMMIT 'g', 'b', 7"), $reply($emulated, "XA COMMIT 'g', 'b', 7"), $version . ': ' . "XA COMMIT 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'SELECT value FROM items'), $reply($emulated, 'SELECT value FROM items'), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA START 'rollback'"), $reply($emulated, "XA START 'rollback'"), $version . ': ' . "XA START 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'UPDATE items SET value = 4'), $reply($emulated, 'UPDATE items SET value = 4'), $version . ': ' . 'UPDATE items SET value = 4');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA END 'rollback'"), $reply($emulated, "XA END 'rollback'"), $version . ': ' . "XA END 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, "XA ROLLBACK 'rollback'"), $reply($emulated, "XA ROLLBACK 'rollback'"), $version . ': ' . "XA ROLLBACK 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($reply($real, 'SELECT value FROM items'), $reply($emulated, 'SELECT value FROM items'), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());

        } finally {
            $real = null;
            $emulated = null;
            $memory?->stop();
            $native->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }
}
