<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TransactionNativeExecutionTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testTransactionFamiliesAgreeWithNativeRepliesAndStoredRows(string $version): void
    {
        $native = Testcontainers::run(new DisposableMySql($version));
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
            $observer = new Observer();
            self::assertSame($observer->observe($real, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE', true), $observer->observe($emulated, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE', true), $version . ': ' . 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'BEGIN WORK', true), $observer->observe($emulated, 'BEGIN WORK', true), $version . ': ' . 'BEGIN WORK');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'UPDATE items SET value = 1', true), $observer->observe($emulated, 'UPDATE items SET value = 1', true), $version . ': ' . 'UPDATE items SET value = 1');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'SAVEPOINT FULL', true), $observer->observe($emulated, 'SAVEPOINT FULL', true), $version . ': ' . 'SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'UPDATE items SET value = 2', true), $observer->observe($emulated, 'UPDATE items SET value = 2', true), $version . ': ' . 'UPDATE items SET value = 2');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'ROLLBACK WORK TO SAVEPOINT FULL', true), $observer->observe($emulated, 'ROLLBACK WORK TO SAVEPOINT FULL', true), $version . ': ' . 'ROLLBACK WORK TO SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'SELECT value FROM items', true), $observer->observe($emulated, 'SELECT value FROM items', true), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'RELEASE SAVEPOINT FULL', true), $observer->observe($emulated, 'RELEASE SAVEPOINT FULL', true), $version . ': ' . 'RELEASE SAVEPOINT FULL');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'COMMIT AND CHAIN NO RELEASE', true), $observer->observe($emulated, 'COMMIT AND CHAIN NO RELEASE', true), $version . ': ' . 'COMMIT AND CHAIN NO RELEASE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'SELECT value FROM items', true), $observer->observe($emulated, 'SELECT value FROM items', true), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'ROLLBACK WORK AND NO CHAIN NO RELEASE', true), $observer->observe($emulated, 'ROLLBACK WORK AND NO CHAIN NO RELEASE', true), $version . ': ' . 'ROLLBACK WORK AND NO CHAIN NO RELEASE');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT', true), $observer->observe($emulated, 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT', true), $version . ': ' . 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'ROLLBACK', true), $observer->observe($emulated, 'ROLLBACK', true), $version . ': ' . 'ROLLBACK');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA START 'g', 'b', 7", true), $observer->observe($emulated, "XA START 'g', 'b', 7", true), $version . ': ' . "XA START 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'UPDATE items SET value = 3', true), $observer->observe($emulated, 'UPDATE items SET value = 3', true), $version . ': ' . 'UPDATE items SET value = 3');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA END 'g', 'b', 7", true), $observer->observe($emulated, "XA END 'g', 'b', 7", true), $version . ': ' . "XA END 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA PREPARE 'g', 'b', 7", true), $observer->observe($emulated, "XA PREPARE 'g', 'b', 7", true), $version . ': ' . "XA PREPARE 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'XA RECOVER', true), $observer->observe($emulated, 'XA RECOVER', true), $version . ': ' . 'XA RECOVER');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA COMMIT 'g', 'b', 7", true), $observer->observe($emulated, "XA COMMIT 'g', 'b', 7", true), $version . ': ' . "XA COMMIT 'g', 'b', 7");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'SELECT value FROM items', true), $observer->observe($emulated, 'SELECT value FROM items', true), $version . ': ' . 'SELECT value FROM items');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA START 'rollback'", true), $observer->observe($emulated, "XA START 'rollback'", true), $version . ': ' . "XA START 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'UPDATE items SET value = 4', true), $observer->observe($emulated, 'UPDATE items SET value = 4', true), $version . ': ' . 'UPDATE items SET value = 4');
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA END 'rollback'", true), $observer->observe($emulated, "XA END 'rollback'", true), $version . ': ' . "XA END 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, "XA ROLLBACK 'rollback'", true), $observer->observe($emulated, "XA ROLLBACK 'rollback'", true), $version . ': ' . "XA ROLLBACK 'rollback'");
            self::assertSame($real->inTransaction(), $emulated->inTransaction());
            self::assertSame($observer->observe($real, 'SELECT value FROM items', true), $observer->observe($emulated, 'SELECT value FROM items', true), $version . ': ' . 'SELECT value FROM items');
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
