<?php

declare(strict_types=1);

namespace Tests\Integration\Definition;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UpgradeDatabaseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'ALTER DATABASE a UPGRADE DATA DIRECTORY NAME',
            'ALTER DATABASE `#mysql50#` UPGRADE DATA DIRECTORY NAME',
            'ALTER DATABASE `#mysql50#missing` UPGRADE DATA DIRECTORY NAME',
            'CREATE DATABASE a',
            'ALTER DATABASE `#mysql50#a` UPGRADE DATA DIRECTORY NAME',
            'CREATE DATABASE `#mysql50#old-name`',
            'CREATE TABLE `#mysql50#old-name`.t(i INT)',
            'INSERT INTO `#mysql50#old-name`.t VALUES (2)',
            'CREATE PROCEDURE `#mysql50#old-name`.p() SELECT 42',
            'CREATE FUNCTION `#mysql50#old-name`.f() RETURNS INT DETERMINISTIC RETURN 42',
            'CREATE EVENT `#mysql50#old-name`.e ON SCHEDULE EVERY 1 DAY DISABLE DO SELECT 1',
            'CREATE VIEW `#mysql50#old-name`.v AS SELECT i FROM `#mysql50#old-name`.t',
            'CREATE TRIGGER `#mysql50#old-name`.tr BEFORE INSERT ON `#mysql50#old-name`.t FOR EACH ROW SET NEW.i = 7',
            'USE `#mysql50#old-name`',
            'ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME',
            'SELECT DATABASE()',
            'SELECT * FROM `old-name`.t',
            'CALL `old-name`.p()',
            'SELECT `old-name`.f()',
            'SHOW EVENTS FROM `old-name`',
            'INSERT INTO `old-name`.t VALUES (3)',
            'SELECT * FROM `old-name`.t',
            'SELECT * FROM `old-name`.v',
            'ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME',
        ];
        yield '5.6' => ['5.6.51', $statements];
        yield '5.7' => ['5.7.44', $statements];
    }

    /**
     * @param list<string> $statements The same ordered history for both databases
     */
    #[DataProvider('providerReleases')]
    public function testUpgradeMatchesAnIsolatedNativeDatabase(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

}
