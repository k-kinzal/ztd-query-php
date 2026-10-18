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
final class PartitionAlterationDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        $clauses = ['RANGE(a) (PARTITION p0 VALUES LESS THAN(10))', 'LIST(a) (PARTITION p0 VALUES IN(1))', 'HASH(a) PARTITIONS 2', 'RANGE(a) SUBPARTITION BY HASH(a) (PARTITION p0 VALUES LESS THAN(10))', 'RANGE(a) SUBPARTITION BY HASH(a) SUBPARTITIONS 2 (PARTITION p0 VALUES LESS THAN(10))'];
        $changes = ['ADD PARTITION', 'ADD PARTITION PARTITIONS 2', 'ADD PARTITION (PARTITION p1 VALUES LESS THAN(20))', 'ADD PARTITION (PARTITION p1 VALUES IN(2))', 'ADD PARTITION (PARTITION p1)', 'ADD PARTITION (PARTITION p1 VALUES LESS THAN(20) (SUBPARTITION s0))', 'REORGANIZE PARTITION p0 INTO (PARTITION q VALUES LESS THAN(10) (SUBPARTITION q0))', 'REORGANIZE PARTITION p0 INTO (PARTITION q VALUES LESS THAN(10))'];
        foreach ($clauses as $clause) {
            foreach ($changes as $change) {
                array_push($statements, 'CREATE TABLE t(a INT) PARTITION BY ' . $clause, 'INSERT INTO t VALUES(1)', 'ALTER TABLE t ' . $change, 'SELECT * FROM t', 'DROP TABLE t');
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Partition definitions across catalog states
     */
    #[DataProvider('providerReleases')]
    public function testPartitionChangesRetainExistingMethodAndSubpartitionConstraints(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
