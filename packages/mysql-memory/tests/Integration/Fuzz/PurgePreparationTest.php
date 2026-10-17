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
final class PurgePreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['DEFAULT(nope)', 'nope', 'DEFAULT(nope)+1', 'BINARY DEFAULT(nope)', 'ROW(1,2)', "DATE 'bad'", 'ABS()', '@@unknown_var', 'CONVERT(1 USING missing)', 'SUM(1)', "JSON_EXTRACT('1','bad')", 'missing_fun(1)', 'd.missing_fun(1)', 'COUNT(*)', 'COUNT(nope)', 'AVG(1) OVER()', 'CAST(1 AS DATETIME(7))', 'IF(FALSE,nope,0)'] as $operand) {
            $statements[] = 'PURGE BINARY LOGS BEFORE ' . $operand;
        }
        $statements[] = 'CREATE DATABASE d';
        $statements[] = 'USE d';
        $statements[] = 'PURGE BINARY LOGS BEFORE missing_fun(1)';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The operand failures in their observed order
     */
    #[DataProvider('providerReleases')]
    public function testPreparationConditionsFollowTheFirstFailure(string $release, array $statements): void
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
