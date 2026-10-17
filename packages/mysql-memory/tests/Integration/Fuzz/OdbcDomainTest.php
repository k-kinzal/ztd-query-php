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
final class OdbcDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4'];
        foreach (['d', 't', 'ts'] as $kind) {
            foreach (['bad', '2024-13-01', '2024-13-01 01:02:03', '839:00:00', "a'b", ''] as $value) {
                $statements[] = 'SELECT { ' . $kind . " '" . str_replace("'", "''", $value) . "' }";
            }
        }
        foreach (['', 'ALLOW_INVALID_DATES', 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'] as $mode) {
            $statements[] = "SET sql_mode='" . $mode . "'";
            foreach (['2024-02-31', '2024-00-01', '0000-00-00', '2024-02-31 01:02:03', '2024-00-01 01:02:03'] as $text) {
                foreach (['d', 'ts'] as $kind) {
                    $escape = '{ ' . $kind . " '" . $text . "' }";
                    array_push($statements, 'SELECT ' . $escape, "SELECT NAME_CONST('value', " . $escape . ')', 'SELECT NAME_CONST(' . $escape . ', 1)');
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $inputs = $release === '5.6.51' ? array_values(array_filter($statements, static fn (string $sql): bool => !str_starts_with($sql, "SELECT NAME_CONST('value',"))) : $statements;
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements ODBC escapes outside their temporal representation domains
     */
    #[DataProvider('providerInputs')]
    public function testUnrepresentableTemporalStringsMatchNative(string $release, array $statements): void
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
