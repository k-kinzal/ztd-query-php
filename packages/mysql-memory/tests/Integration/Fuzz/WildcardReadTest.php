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
final class WildcardReadTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(v INT,n INT)', 'INSERT INTO t VALUES(1,2)'];
        foreach (['t.*', '``.*', '1,t.*', 't.* FROM DUAL', '1,t.* FROM DUAL', 't.* FROM absent', 'absent.* FROM t', 't.* UNION SELECT 1', '``.* FROM t', 'd.``.* FROM t', 'missing.``.* FROM t', '``.``.*', '``.``.* FROM absent', '``.t.*', '``.t.* FROM absent'] as $body) {
            foreach (['SELECT ' . $body, 'SELECT (SELECT ' . $body . ')', 'SELECT EXISTS(SELECT ' . $body . ')', 'SELECT 1 IN(SELECT ' . $body . ')'] as $sql) {
                $statements[] = $sql;
                $statements[] = 'CREATE VIEW v' . count($statements) . ' AS ' . $sql;
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Ordinary reads, subquery predicates, and view definitions
     */
    #[DataProvider('providerInputs')]
    public function testWildcardRolesPreserveReadConditionsAndExistenceResults(string $release, array $statements): void
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
