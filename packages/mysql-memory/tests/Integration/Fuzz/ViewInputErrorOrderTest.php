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
final class ViewInputErrorOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [];
            foreach (['CREATE VIEW', 'ALTER VIEW'] as $verb) {
                foreach (['ROW(1,2)', "ROW(1,2), 'x' COLLATE missing", 'ROW(1,2), @@missing'] as $values) {
                    $queries[] = "$verb d.v AS SELECT SQL_NO_CACHE $values FROM t";
                    $queries[] = "$verb d.v AS SELECT SQL_NO_CACHE $values FROM t, t";
                }
            }
            $original = in_array($release, ['8.4.7', '9.1.0'], true) ? [(string) file_get_contents(__DIR__ . '/fixtures/view-input-order.sql')] : [];
            yield $release => [$release, [...$original, ...$queries, 'CREATE DATABASE d', 'USE d', ...$queries, 'CREATE TABLE t(a INT)', ...$queries, 'CREATE VIEW d.v AS SELECT 1 x', ...$queries]];
        }
    }

    /**
     * @param list<string> $statements Complete view operands, earlier input conditions and catalog states
     */
    #[DataProvider('providerInputs')]
    public function testPreparationErrorsReturnThroughInputDiagnosticOrdering(string $release, array $statements): void
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
