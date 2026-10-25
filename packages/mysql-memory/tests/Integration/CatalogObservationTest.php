<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Observation\CatalogState;
use Fuzz\Observation\Observer;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class CatalogObservationTest extends TestCase
{
    public function testRowsOutsideTheConventionalFixtureRemainObservable(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['outside']);
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection->exec('CREATE TABLE outside.items (id INT)');
            $connection->exec('INSERT INTO outside.items VALUES (1), (1)');
            $databases = (new CatalogState())->databases($connection);

            self::assertSame([[1], [1]], $databases['outside']['rows']['items']);
            self::assertNotNull($databases['outside']['definitions']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testAnIndependentConnectionObservesCommittedVisibility(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['outside']);
        try {
            $writer = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $reader = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $writer->exec('CREATE TABLE outside.items (id INT)');
            $writer->exec('BEGIN');
            $writer->exec('INSERT INTO outside.items VALUES (42)');
            $observer = new Observer();
            self::assertSame([[42]], $observer->tables($writer, 'outside')['items']);
            self::assertSame([], (new CatalogState())->databases($reader)['outside']['rows']['items']);
            $writer->exec('COMMIT');

            self::assertSame([[42]], (new CatalogState())->databases($reader)['outside']['rows']['items']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testViewsAreRecordedWithoutExecutingTheirBodies(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['outside']);
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection->exec('CREATE TABLE outside.items (id INT)');
            $connection->exec('CREATE VIEW outside.current_items AS SELECT id FROM outside.items');
            $connection->exec('DROP TABLE outside.items');
            $observer = new Observer();

            self::assertSame([], $observer->tables($connection, 'outside'));
            $definitions = $observer->definitions($connection, 'outside');
            self::assertNotNull($definitions);
            self::assertIsArray($definitions['relations']);
            self::assertArrayHasKey('current_items', $definitions['relations']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testAccountAndRoutineDefinitionsAreCapturedWithoutCallingThem(): void
    {
        $server = MemoryDatabase::start('8.4.7', ['outside']);
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $observer = new CatalogState();
            $before = $observer->capture($connection, '8.4.7');
            $connection->exec("CREATE USER 'recorded'@'%' ACCOUNT LOCK");
            $connection->exec('CREATE PROCEDURE outside.recorded() SELECT 1');
            $after = $observer->capture($connection, '8.4.7');

            self::assertNotSame($before['raw']['mysql.user'], $after['raw']['mysql.user']);
            self::assertNotSame($before['comparable']['mysql.user'], $after['comparable']['mysql.user']);
            self::assertNotSame($before['raw']['information_schema.ROUTINES'], $after['raw']['information_schema.ROUTINES']);
            self::assertNotSame($before['comparable']['information_schema.ROUTINES'], $after['comparable']['information_schema.ROUTINES']);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }
}
