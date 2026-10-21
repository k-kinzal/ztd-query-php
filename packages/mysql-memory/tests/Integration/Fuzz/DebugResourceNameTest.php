<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DebugResourceNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $fixture = file_get_contents(__DIR__ . '/fixtures/debug-resource-group.sql');
        self::assertIsString($fixture);
        $statements = ['CREATE DATABASE d', 'USE d', $fixture, 'CREATE RESOURCE GROUP active_group TYPE=USER', 'ALTER RESOURCE GROUP active_group THREAD_PRIORITY=1', 'DROP RESOURCE GROUP active_group', 'CREATE RESOURCE GROUP `' . str_repeat('n', 65) . '` TYPE=USER'];
        foreach (['SET RESOURCE GROUP', 'DROP RESOURCE GROUP', 'ALTER RESOURCE GROUP', 'CREATE RESOURCE GROUP'] as $head) {
            foreach (['g', '`' . str_repeat('n', 65) . '`'] as $name) {
                foreach (['', ' FOR 1', ' FOR 1 broken', ' broken', ' FOR ?', ' FOR 0x0f', ' FOR 18446744073709551615'] as $tail) {
                    $statements[] = 'SHOW PARSE_TREE ' . $head . ' ' . $name . $tail;
                    $statements[] = $head . ' ' . $name . $tail;
                }
            }
        }
        $statements = [...$statements, 'SHOW PARSE_TREE SELECT BINARY 1 +', 'SHOW PARSE_TREE SELECT CAST(1 AT LOCAL AS SIGNED) +'];
        foreach (['8.0.44', '8.1.0', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Complete and unfinished inspections with contextual and grammar conditions
     */
    #[DataProvider('providerReleases')]
    public function testDebugInspectionPrecedesContextualNamesAndPreservesGrammarRefusals(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $bootstrap = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password);
            $priorities = (new \Fuzz\Target\Servers())->resourcePriorities($bootstrap, $release);
            $server = Server::start($release, resourcePriorities: $priorities);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);
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
