<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AlterTransactionOptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $counterexample = (string) file_get_contents(__DIR__ . '/fixtures/alter-transaction-option.sql');
        $alterations = [
            $counterexample,
            'ALTER TABLE t START TRANSACTION',
            'ALTER TABLE t WITH VALIDATION, START TRANSACTION',
            'ALTER TABLE t START TRANSACTION, WITHOUT VALIDATION',
            'ALTER TABLE t START TRANSACTION, ADD COLUMN g INT',
            'ALTER TABLE t ENGINE=bad START TRANSACTION',
            'ALTER TABLE t START TRANSACTION ENGINE=bad',
            'ALTER TABLE t ALGORITHM=bad, START TRANSACTION',
            'ALTER TABLE t START TRANSACTION, ALGORITHM=bad',
            'ALTER TABLE missing.t START TRANSACTION',
            'ALTER TABLE t START TRANSACTION, ADD COLUMN g INT AS (UUID())',
        ];
        $statements = [...$alterations, 'CREATE DATABASE d', 'USE d', ...$alterations, 'CREATE TABLE t(a INT)', ...$alterations,
            "PREPARE alteration FROM 'ALTER TABLE t START TRANSACTION'", 'EXECUTE alteration', 'DEALLOCATE PREPARE alteration',
            'START TRANSACTION', 'INSERT INTO t VALUES(1)', 'ALTER TABLE t START TRANSACTION', 'ROLLBACK', 'SELECT * FROM t',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements ALTER options across database, table, prepared-statement and transaction states
     */
    #[DataProvider('providerReleases')]
    public function testCreationTransactionOptionFollowsItsContextualAndInvocationPhases(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && str_starts_with($sql, 'ALTER TABLE'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && str_starts_with($sql, 'ALTER TABLE'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
