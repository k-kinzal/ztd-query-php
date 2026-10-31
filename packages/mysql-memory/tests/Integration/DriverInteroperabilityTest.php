<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use mysqli_result;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DriverInteroperabilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }

    #[DataProvider('providerReleases')]
    public function testPdoWritesAreVisibleThroughMysqliOnTheSelectedRelease(string $version): void
    {
        $database = MemoryDatabase::start($version, ['shop']);
        try {
            $pdo = new PDO($database->dsn('shop'), 'root', '');
            $mysqli = new mysqli($database->host, 'root', '', 'shop', $database->port);
            $pdoVersion = $pdo->query('SELECT VERSION()');
            $mysqliVersion = $mysqli->query('SELECT VERSION()');
            self::assertNotFalse($pdoVersion);
            self::assertInstanceOf(mysqli_result::class, $mysqliVersion);
            self::assertSame($version, $pdoVersion->fetchColumn());
            self::assertSame([$version], $mysqliVersion->fetch_row());
            $pdo->exec('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20))');
            $pdo->exec("INSERT INTO items VALUES (1, 'pen')");
            $items = $mysqli->query('SELECT * FROM items');
            self::assertInstanceOf(mysqli_result::class, $items);
            self::assertSame(['1', 'pen'], $items->fetch_row());
            $mysqli->close();
        } finally {
            $database->stop();
        }
    }

}
