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
final class DatabasePriorityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $requests = [
            'DELETE x FROM priority_domain.t', 'DELETE t FROM priority_domain.t',
            'DELETE t FROM priority_domain.t AS t', 'DELETE t,t FROM priority_domain.t',
            'DELETE priority_domain.t FROM priority_domain.t',
            'DELETE x FROM (SELECT 1) AS d', 'DELETE d FROM (SELECT 1) AS d',
            'DELETE x FROM (SELECT HIGH_PRIORITY 1) AS d',
            'EXPLAIN DELETE x FROM priority_domain.t',
            'SHOW FUNCTION CODE f', 'SHOW FUNCTION CODE priority_domain.f',
            'SHOW PROCEDURE CODE p', 'SHOW PROCEDURE CODE priority_domain.p',
            'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SIGNAL c',
            'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT BINARY 1',
        ];
        $inputs = ['CREATE DATABASE priority_domain', 'CREATE TABLE priority_domain.t(a INT)', ...$requests, 'USE priority_domain', ...$requests];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Object names before and after selecting their implicit database
     */
    #[DataProvider('providerStatements')]
    public function testDefaultDatabasePrecedesLaterObjectDiagnostics(string $release, array $statements): void
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
