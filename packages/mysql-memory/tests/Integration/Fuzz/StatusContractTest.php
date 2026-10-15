<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use Fuzz\Target\Status\Listing;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StatusContractTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, DisposableMySql}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $first = new DisposableMySql($release);
            $second = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_STATUS_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65534]]);
            if (is_int($port)) {
                $first->withPortStrategy(new StaticPortStrategy($port));
                $second->withPortStrategy(new StaticPortStrategy($port + 1));
            }
            yield $release => [$release, $first, $second];
        }
    }

    #[DataProvider('providerReleases')]
    public function testCounterHistoriesAndPhysicalValuesAcrossIndependentInstances(string $release, DisposableMySql $firstDefinition, DisposableMySql $secondDefinition): void
    {
        $first = Testcontainers::run($firstDefinition);
        $server = null;
        try {
            $second = Testcontainers::run($secondDefinition);
            try {
                $left = $first->getData(Endpoint::class);
                $right = $second->getData(Endpoint::class);
                $native = new PDO('mysql:host=' . $left->host . ';port=' . $left->port, $left->username, $left->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $another = new PDO('mysql:host=' . $right->host . ';port=' . $right->port, $right->username, $right->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $server = Server::start($release);
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observations = array_map(static function (PDO $connection) use ($memory): array {
                    $connection->exec('CREATE DATABASE d');
                    $connection->exec('USE d');
                    $connection->exec('SET NAMES utf8mb4');
                    $connection->exec('CREATE TABLE t (id INT)');
                    $connection->exec('INSERT INTO t VALUES (1), (2)');
                    $rows = $connection->query('SELECT * FROM t');
                    self::assertNotFalse($rows);
                    self::assertSame([[1], [2]], $rows->fetchAll(PDO::FETCH_NUM));

                    return array_map(static function (string $sql) use ($connection, $memory): string {
                        $contract = Listing::capture($connection, $sql);
                        self::assertInstanceOf(Listing::class, $contract);
                        $observation = $contract->comparable((new Observer())->observe($connection, $sql, true), $connection === $memory);
                        self::assertSame([Listing::CONTRACT], $observation['contracts'] ?? null);

                        return serialize($observation);
                    }, ['SHOW STATUS', 'SHOW LOCAL STATUS', 'SHOW SESSION STATUS', 'SHOW GLOBAL STATUS']);
                }, [$native, $another, $memory]);

                self::assertSame($observations[0], $observations[1], $release . ': independent native instances');
                self::assertSame($observations[0], $observations[2], $release . ': memory');
            } finally {
                $second->stop();
            }
        } finally {
            $server?->stop();
            $first->stop();
        }
    }
}
