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
final class SourceUuidInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4'];
        $values = ['', 'bad', str_repeat('x', 127), str_repeat('x', 128), str_repeat('x', 129), str_repeat('x', 127) . '猫', str_repeat('x', 126) . '猫', "bad\0rest", "bad\nrest", '3e11fa47-71ca-11e1-9e33-c80aa9429562', '3e11fa47-71ca-11e1-9e33-c80aa9429562 rest', "3e11fa47-71ca-11e1-9e33-c80aa9429562\nrest"];
        foreach ($values as $value) {
            $quoted = "'" . str_replace(['\\', "'", "\0"], ['\\\\', "''", '\\0'], $value) . "'";
            $option = 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=' . $quoted;
            foreach ([$option, $option . ' TRAILING', $option . ",SOURCE_HOST='a\\nb'", "SOURCE_HOST='a\\nb'," . $option, 'SOURCE_DELAY=2147483648,' . $option, $option . ',SOURCE_DELAY=2147483648', 'SOURCE_HEARTBEAT_PERIOD=5000000,' . $option, $option . ',SOURCE_HEARTBEAT_PERIOD=5000000', "SOURCE_PASSWORD='" . str_repeat('p', 33) . "'," . $option, $option . ",SOURCE_PASSWORD='" . str_repeat('p', 33) . "'"] as $options) {
                $statements[] = 'CHANGE REPLICATION SOURCE TO ' . $options;
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_UUID_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
            }
            yield $release => [$release, $definition, $statements];
        }
    }

    /**
     * @param list<string> $statements UUID operands and earlier or later input refusals
     */
    #[DataProvider('providerInputs')]
    public function testUuidDomainAndDiagnosticPrefixMatchNative(string $release, DisposableMySql $definition, array $statements): void
    {
        $container = Testcontainers::run($definition);
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
