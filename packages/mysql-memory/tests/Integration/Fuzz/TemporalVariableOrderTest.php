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
final class TemporalVariableOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [
                'SELECT @@missing, TIMESTAMP \'text\'',
                'SELECT TIMESTAMP \'text\', @@missing',
                'SELECT @@GLOBAL.missing.name, TIMESTAMP \'text\'',
                'SELECT TIMESTAMP \'text\', @@GLOBAL.missing.name',
                'SELECT @@SESSION.max_connections, TIMESTAMP \'text\'',
                'SELECT TRIM(TIMESTAMP \'text\' FROM @@missing)',
                'SELECT TIMESTAMPADD(DAY, TIMESTAMP \'text\', @@missing)',
                'SELECT TIMESTAMPDIFF(DAY, TIMESTAMP \'text\', @@missing)',
                'SELECT @@missing, DATE \'text\', CAST(1 AS DATETIME(7))',
                'SELECT @@missing, DATE \'text\' FROM missing',
                'INSERT INTO missing.t VALUES(@@missing, TIMESTAMP \'text\')',
                'CREATE VIEW d.v AS SELECT @@missing, TIMESTAMP \'text\'',
                'SELECT DATE \'bad\', @@missing COLLATE absent',
                'CREATE VIEW d.v AS SELECT TIMESTAMP \'text\', @@missing',
                'CREATE VIEW v AS SELECT @@missing, TIMESTAMP \'text\'',
                'CREATE VIEW v AS SELECT TIMESTAMP \'text\', @@missing',
                'CREATE VIEW d.v AS SELECT @a, TIMESTAMP \'text\'',
                'SELECT @@missing, (SELECT SQL_NO_CACHE 1), TIMESTAMP \'text\'',
                'SELECT (SELECT SQL_NO_CACHE 1), @@missing, TIMESTAMP \'text\'',
                'SELECT @@missing, DATE \'text\', (SELECT SQL_NO_CACHE 1)',
                'SELECT (SELECT SQL_NO_CACHE 1), TIMESTAMP \'text\', @@missing',
                'SELECT @@GLOBAL.sql_slave_skip_counter, DATE \'text\'',
                'SELECT DATE \'text\', @@GLOBAL.sql_slave_skip_counter',
                'SELECT @@GLOBAL.sql_slave_skip_counter, DATE \'2026-10-20\'',
                'SELECT TRIM(DATE \'text\' FROM @a)',
                'CREATE VIEW d.v AS SELECT @a := DATE \'text\'',
            ];
            $original = in_array($release, ['8.4.7', '9.1.0'], true) ? [(string) file_get_contents(__DIR__ . '/fixtures/temporal-variable-order.sql')] : [];
            yield $release => [$release, [...$queries, 'CREATE DATABASE d', 'USE d', ...$original, ...$queries]];
        }
    }

    /**
     * @param list<string> $statements Contextual variables, temporal values, warnings and default database states
     */
    #[DataProvider('providerInputs')]
    public function testContextualValuesKeepNativeErrorAndWarningOrder(string $release, array $statements): void
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
