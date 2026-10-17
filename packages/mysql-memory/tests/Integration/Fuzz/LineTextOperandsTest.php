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
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LineTextOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the retained fuzz input cannot be read
     */
    public static function providerReleases(): iterable
    {
        $retained = file_get_contents(__DIR__ . '/fixtures/show-parse-tree-line-feed.sql');
        if ($retained === false) {
            throw new RuntimeException('The retained fuzz input is missing.');
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $legacy = version_compare($release, '8.0', '<');
            $change = $legacy ? 'CHANGE MASTER TO MASTER_HOST = ' : 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = ';
            $start = $legacy ? 'START SLAVE UNTIL MASTER_LOG_FILE = ' : 'START REPLICA UNTIL SOURCE_LOG_FILE = ';
            $position = $legacy ? ', MASTER_LOG_POS = 4' : ', SOURCE_LOG_POS = 4';
            $statements = [];
            foreach (['latin1', 'utf8mb4'] as $charset) {
                $statements[] = 'SET NAMES ' . $charset;
                foreach (["a\nb", "a\0b\nc", "a\nb\0c", "a\rb", "a\tb", str_repeat('x', 140) . "\n", str_repeat('あ', 60) . "\n"] as $value) {
                    $quoted = "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'", "\0" => '\\0']) . "'";
                    $cases = [$change . $quoted, $start . $quoted . $position];
                    $statements = [...$statements, ...$cases];
                    if (version_compare($release, '8.1', '>=')) {
                        $statements = [...$statements, ...array_map(static fn (string $sql): string => 'SHOW PARSE_TREE ' . $sql, $cases)];
                    }
                }
            }
            $statements[] = $change . "'a\nb' TRAILING";
            if (version_compare($release, '5.7', '>=')) {
                $statements[] = "CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE=('d.a\nb')";
                $statements[] = ($legacy ? 'START SLAVE' : 'START REPLICA') . " FOR CHANNEL 'a\nb'";
            }
            if (!$legacy) {
                $statements = [...$statements, "START GROUP_REPLICATION USER='a\nb'", "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a\nb' NAME 'c'", "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b' DESCRIPTION 'c\nd'", "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b' DESCRIPTION"];
            }
            if (version_compare($release, '8.1', '>=')) {
                $statements[] = $retained;
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testRestrictedSystemTextAndEarlierConditionsMatchNative(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, 0)), $statements);

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
