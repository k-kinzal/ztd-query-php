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
final class StorageOptionInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $roots = ["CREATE TABLESPACE ts ADD DATAFILE 'f'", 'DROP TABLESPACE ts', "CREATE LOGFILE GROUP g ADD UNDOFILE 'f'", "ALTER LOGFILE GROUP g ADD UNDOFILE 'f'", "CREATE UNDO TABLESPACE ts ADD DATAFILE 'f'", 'ALTER UNDO TABLESPACE ts SET ACTIVE', 'DROP UNDO TABLESPACE ts'];
        $options = ['ENGINE InnoDB ENGINE InnoDB', "COMMENT 'a' COMMENT 'b'", 'NODEGROUP 1 NODEGROUP 2', 'NODEGROUP 65535 NODEGROUP 1', 'NODEGROUP 65535 NODEGROUP 65535', 'NODEGROUP 1 NODEGROUP 65535', 'FILE_BLOCK_SIZE 0 FILE_BLOCK_SIZE 4K', 'FILE_BLOCK_SIZE 4K FILE_BLOCK_SIZE 0', 'FILE_BLOCK_SIZE 4K FILE_BLOCK_SIZE 3T', 'INITIAL_SIZE 3T', 'INITIAL_SIZE 2147483648K', 'INITIAL_SIZE 1.5', 'NODEGROUP 1.5', 'NO_WAIT NO_WAIT', 'NO_WAIT WAIT NO_WAIT'];
        $inputs = [];
        foreach ($roots as $root) {
            foreach ($options as $option) {
                $inputs[] = $root . ' ' . $option;
            }
            $inputs[] = $root . ' ENGINE InnoDB ENGINE InnoDB broken';
        }
        foreach ($options as $option) {
            $sql = $roots[0] . ' ' . $option;
            array_push($inputs, 'SHOW PARSE_TREE ' . $sql, $sql . ' broken', 'CREATE PROCEDURE p() ' . $sql);
        }
        $inputs[] = 'SHOW PARSE_TREE DROP UNDO TABLESPACE ts ENGINE InnoDB ENGINE InnoDB';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $inputs Conflicts, sentinels and refusal boundaries across storage statement families
     */
    #[DataProvider('providerInputs')]
    public function testInputRefusalsPreserveNativePhaseAndPrecedence(string $release, array $inputs): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            $differences = array_filter(array_map(static function (string $sql) use ($native, $memory): ?array {
                $expected = (new Native())->observe($native, $sql);
                $actual = (new Native())->observe($memory, $sql);
                return serialize($expected) === serialize($actual) ? null : [$sql, $expected, $actual];
            }, $inputs));

            self::assertSame([], $differences);
            $native->close();
            $memory->close();
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
