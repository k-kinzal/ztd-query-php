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
final class LockNameInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $long = '`' . str_repeat('a', 65) . '`';
            $requests = [
                'LOCK TABLES t READ, d.t AS a LOW_PRIORITY WRITE',
                'LOCK TABLES d.t AS a LOW_PRIORITY WRITE, t READ',
                'LOCK TABLES t LOW_PRIORITY WRITE, d.t AS a LOW_PRIORITY WRITE',
                'LOCK TABLES t READ, d.t AS a READ,?',
                'LOCK TABLES t LOW_PRIORITY WRITE,?',
                'LOCK TABLES d.t LOW_PRIORITY WRITE,?',
                'LOCK TABLES ' . $long . ' LOW_PRIORITY WRITE',
                'LOCK TABLES t AS ' . $long . ' LOW_PRIORITY WRITE',
                'LOCK TABLES t READ, d.' . $long . ' LOW_PRIORITY WRITE',
                'LOCK TABLES d.t READ, d.t WRITE, t LOW_PRIORITY WRITE',
            ];
            $inputs = ['CREATE DATABASE d', 'CREATE TABLE d.t(a INT)'];
            foreach (['DO 0', 'USE d'] as $selection) {
                $inputs[] = $selection;
                foreach ($requests as $sql) {
                    $inputs[] = $sql;
                    $inputs[] = 'UNLOCK TABLES';
                }
            }
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Database selection, completed warnings, malformed suffixes and competing identifiers
     */
    #[DataProvider('providerStatements')]
    public function testLockNameInputPriorityMatchesNative(string $release, array $statements): void
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
