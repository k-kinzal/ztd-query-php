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
final class AlterLayoutTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [
            'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)',
            'CREATE INDEX i ON t(missing)',
            'ALTER TABLE t ADD INDEX i(missing)',
            'ALTER TABLE t ADD INDEX i(a,a)',
            'ALTER TABLE t ADD PRIMARY KEY(a),ADD PRIMARY KEY(a)',
            "CREATE INDEX `PRIMARY` ON t(missing,(CAST(FALSE AT TIME ZONE 't' AS DATETIME)))",
            "CREATE INDEX i ON t(a,a,(CAST(FALSE AT TIME ZONE 't' AS DATETIME)))",
            "ALTER TABLE t ADD PRIMARY KEY((CAST(FALSE AT TIME ZONE 't' AS DATETIME)))",
            'CREATE INDEX i ON t(missing,((SELECT 1)))',
            "CREATE INDEX i ON t((CAST(missing AT TIME ZONE 't' AS DATETIME)))",
            "CREATE INDEX i ON t(missing,(CAST(CURRENT_TIMESTAMP AT TIME ZONE 't' AS DATETIME)))",
            'SELECT * FROM t', 'SHOW CREATE TABLE t',
            'ALTER TABLE t ADD missing INT', 'CREATE INDEX i ON t(missing)', 'SELECT * FROM t',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Table state, conflicting changes and a successful retry after adding the missing column
     */
    #[DataProvider('providerInputs')]
    public function testStateDependentLayoutErrorsMatchNativeQueries(string $release, array $statements): void
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
