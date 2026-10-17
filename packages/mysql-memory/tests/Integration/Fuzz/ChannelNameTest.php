<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ChannelNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChannelReleases(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerChannelReleases')]
    public function testDirectConstructionUsesTheSemanticChannelNameWithoutInputSql(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid input';
                $nodes = array_map(static fn (string $text): ShowRelaylogEvents => new ShowRelaylogEvents(channel: new Text($text)), ["abc\0ignored", "abc\0\nignored", "\0\nignored", "a\rb"]);
                $expected = array_map(static fn (ShowRelaylogEvents $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()))), $nodes);
                $actual = array_map(static fn (ShowRelaylogEvents $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                self::assertSame($expected, $actual);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (["'AbC'", "'\\0\\nb'", "'a\\nb'", "'a\nb'", "'a\\0\\nb'", "'a\\rb'", "''", "'a\\0b'", "'" . str_repeat('猫é', 35) . "\n'"] as $name) {
            foreach (['SHOW RELAYLOG EVENTS', 'SHOW SLAVE STATUS', 'STOP SLAVE', 'START SLAVE', 'RESET SLAVE', 'FLUSH RELAY LOGS', "CHANGE MASTER TO MASTER_HOST='x'", 'CHANGE REPLICATION FILTER REPLICATE_DO_DB=()'] as $head) {
                $statements[] = $head . ' FOR CHANNEL ' . $name;
            }
        }
        $statements = [...$statements,
            "SHOW PARSE_TREE SHOW RELAYLOG EVENTS IN 'log' FOR CHANNEL 'a\\nb'",
            "SHOW PARSE_TREE SHOW RELAYLOG EVENTS FOR CHANNEL '猫\na'",
            "SHOW PARSE_TREE SHOW SLAVE STATUS FOR CHANNEL 'a\\nb'",
            "SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb' trailing",
            "SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb' 'unfinished",
            "CREATE PROCEDURE p() SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb'",
            "CREATE PROCEDURE p() BEGIN IF 0 THEN SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb'; END IF; END",
            "CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb'; END",
            "SET sql_mode='NO_BACKSLASH_ESCAPES'",
            "SHOW RELAYLOG EVENTS FOR CHANNEL 'a\\nb'",
            "SHOW RELAYLOG EVENTS FOR CHANNEL 'a\nb'",
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Channel operands and preceding or following conditions
     */
    #[DataProvider('providerReleases')]
    public function testInputRefusalPrecedesDebugAndStorageExecution(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
                $reset = str_starts_with($release, '5.') ? 'RESET SLAVE ALL' : 'RESET REPLICA ALL';
                $observe = static function (mysqli $connection, string $sql) use ($reset): string {
                    $restored = (new Native())->observe($connection, $reset);
                    $error = $restored['error'] ?? [0];
                    self::assertContains($error[0], [0, 1794], 'The isolated channel fixture must be empty or already unconfigured.');
                    $connection->query('DO 0');

                    return serialize((new Native())->observe($connection, $sql));
                };
                $expected = array_map(static fn (string $sql): string => $observe($native, $sql), $statements);
                $actual = array_map(static fn (string $sql): string => $observe($memory, $sql), $statements);

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
