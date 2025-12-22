<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * Integration tests for ZtdPdo error handling with SQLite.
 *
 * @requires extension pdo_sqlite
 */
#[CoversNothing]
#[Large]
final class SqliteErrorHandlingTest extends TestCase
{
    public function testUnsupportedSqlWithExceptionBehavior(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        $config = new ZtdConfig(UnsupportedSqlBehavior::Exception);
        $ztd = ZtdPdo::fromPdo($rawPdo, $config);

        $this->expectException(ZtdPdoException::class);
        $ztd->exec('PRAGMA table_info(sqlite_master)');
    }

    public function testUnsupportedSqlWithIgnoreBehavior(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        $config = new ZtdConfig(UnsupportedSqlBehavior::Ignore);
        $ztd = ZtdPdo::fromPdo($rawPdo, $config);

        $result = $ztd->exec('PRAGMA table_info(sqlite_master)');
        self::assertSame(0, $result);
    }

    public function testUnsupportedSqlWithNoticeBehavior(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        $config = new ZtdConfig(UnsupportedSqlBehavior::Notice);
        $ztd = ZtdPdo::fromPdo($rawPdo, $config);

        $noticeEmitted = false;
        set_error_handler(function (int $errno) use (&$noticeEmitted): bool {
            if ($errno === E_USER_NOTICE) {
                $noticeEmitted = true;
            }
            return true;
        });

        try {
            $result = $ztd->exec('PRAGMA table_info(sqlite_master)');
            self::assertSame(0, $result);
            self::assertTrue($noticeEmitted, 'Expected E_USER_NOTICE to be emitted');
        } finally {
            restore_error_handler();
        }
    }

    public function testPreparedStatementInsertAndSelect(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO items (value) VALUES ('test_value')");

        $stmt = $ztdPdo->prepare('SELECT * FROM items WHERE value = ?');
        self::assertNotFalse($stmt);

        $stmt->execute(['test_value']);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        self::assertCount(1, $rows);
        self::assertSame('test_value', $rows[0]['value']);
    }

    public function testPreparedStatementWithBindValue(): void
    {
        $rawPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $rawPdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');

        $ztdPdo = ZtdPdo::fromPdo($rawPdo, null);

        $ztdPdo->exec("INSERT INTO items (value) VALUES ('bound_value')");

        $stmt = $ztdPdo->prepare('SELECT * FROM items WHERE value = :val');
        self::assertNotFalse($stmt);

        $stmt->bindValue(':val', 'bound_value');
        $stmt->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        self::assertCount(1, $rows);
        self::assertSame('bound_value', $rows[0]['value']);
    }
}
