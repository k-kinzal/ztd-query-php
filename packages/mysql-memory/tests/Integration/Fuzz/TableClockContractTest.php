<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Fixture;
use Fuzz\Target\Observer;
use Fuzz\Target\TableTimes;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TableClockContractTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testIndependentServersValidateTheirOwnFixtureClocks(string $release): void
    {
        $first = Testcontainers::run(new DisposableMySql($release));
        try {
            $firstEndpoint = $first->getData(Endpoint::class);
            $second = Testcontainers::run((new DisposableMySql($release))->withPortStrategy(new \Testcontainers\Containers\PortStrategy\RandomPortStrategy()));
            try {
                $secondEndpoint = $second->getData(Endpoint::class);
                $server = Server::start($release);
                try {
                    $connections = [new PDO('mysql:host=' . $firstEndpoint->host . ';port=' . $firstEndpoint->port, $firstEndpoint->username, $firstEndpoint->password), new PDO('mysql:host=' . $secondEndpoint->host . ';port=' . $secondEndpoint->port, $secondEndpoint->username, $secondEndpoint->password), new PDO($server->dsn(), 'root', '')];
                    $observed = array_map(static function (PDO $pdo) use ($release): array {
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->exec('CREATE DATABASE clock_contract');
                        $pdo->exec('USE clock_contract');
                        $clock = new TableTimes($release);
                        array_map(static function (string $sql) use ($pdo, $clock): void {
                            $clock->execute($pdo, $sql);
                        }, (new Fixture())->statements());
                        $observer = new Observer();
                        $raw = $observer->observe($pdo, 'SHOW TABLE STATUS', true);
                        $raw['tables'] = $observer->tables($pdo, 'clock_contract');
                        usleep(1100000);

                        return [$raw, $clock->comparable($raw)];
                    }, $connections);

                    self::assertNotSame($observed[0][0], $observed[1][0]);
                    self::assertSame($observed[0][1], $observed[1][1]);
                    self::assertSame($observed[0][1], $observed[2][1]);
                    self::assertArrayHasKey('contracts', $observed[0][1]);
                    $connections = [];
                } finally {
                    $server->stop();
                }
            } finally {
                $second->stop();
            }
        } finally {
            $first->stop();
        }
    }
}
