<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

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
final class CreateUserHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a IDENTIFIED WITH missing, CURRENT_USER',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS CURRENT_USER, a IDENTIFIED WITH missing',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a IDENTIFIED WITH missing, b IDENTIFIED WITH missing2, CURRENT_USER',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a, b IDENTIFIED WITH missing, c',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a, a, b IDENTIFIED WITH missing',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a IDENTIFIED WITH missing, a',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a, b IDENTIFIED WITH missing, a',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER a IDENTIFIED WITH missing, CURRENT_USER',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER CURRENT_USER, a IDENTIFIED WITH missing',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing, a IDENTIFIED WITH missing2',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
            'DROP USER IF EXISTS a, b, c',
            'CREATE USER IF NOT EXISTS a IDENTIFIED WITH missing, CURRENT_USER AND IDENTIFIED BY \'x\' AND IDENTIFIED WITH \'also_missing\'',
            'SELECT User FROM mysql.user WHERE User IN (\'a\', \'b\', \'c\') ORDER BY User',
        ];
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The account history to compare
     */
    #[DataProvider('providerReleases')]
    public function testCreationVisitsEveryAccountBeforeReturningTheFirstError(string $release, array $statements): void
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
