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
final class PartitionDefinitionDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $definitions = [
            'PARTITION p0,PARTITION p1 (SUBPARTITION s1)',
            'PARTITION p0 (SUBPARTITION s0),PARTITION p1',
            'PARTITION p0 (SUBPARTITION s0),PARTITION p1 (SUBPARTITION s1,SUBPARTITION s2)',
            'PARTITION p0 (SUBPARTITION s0,SUBPARTITION s1),PARTITION p1 (SUBPARTITION s2)',
        ];
        $statements = [];
        foreach (['', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)'] as $setup) {
            if ($setup !== '') {
                $statements[] = $setup;
            }
            foreach (['t', 'missing.t'] as $name) {
                foreach ($definitions as $definition) {
                    $statements[] = 'ALTER TABLE ' . $name . ' REORGANIZE PARTITION old INTO (' . $definition . ')';
                    $statements[] = 'ALTER TABLE ' . $name . ' ADD PARTITION (' . $definition . ')';
                    $statements[] = 'CREATE TABLE ' . $name . '(a INT) PARTITION BY HASH(a) (' . $definition . ')';
                    $statements[] = 'CREATE TABLE ' . $name . '(a INT) PARTITION BY RANGE(a) SUBPARTITION BY HASH(a) (' . $definition . ')';
                }
            }
        }
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/subpartition-count.sql');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Partition definitions across catalog states
     */
    #[DataProvider('providerReleases')]
    public function testPartitionDefinitionRefusalsPreserveInputPriority(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
