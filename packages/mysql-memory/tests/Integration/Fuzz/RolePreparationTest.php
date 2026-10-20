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
final class RolePreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ["GRANT '' TO u", 'GRANT r TO r', "GRANT r TO ''", "CREATE ROLE ''", "CREATE ROLE IF NOT EXISTS ''"];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete account instructions whose invocation conditions differ from preparation
     */
    #[DataProvider('providerReleases')]
    public function testBinaryPreparationRetainsCompleteRoleInstructionsAndUnsupportedCreateCommands(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $history = array_map(static function (string $setup) use ($native, $memory, $statements): array {
                    array_map(static function (string $sql) use ($native, $memory): void {
                        $native->query($sql);
                        $memory->query($sql);
                    }, explode('; ', $setup));
                    $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: true)), $statements);
                    $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: true)), $statements);
                    return [$expected, $actual];
                }, ['SET NAMES utf8mb4', 'CREATE USER u; CREATE ROLE r']);
                self::assertSame(array_column($history, 0), array_column($history, 1), $release . ':COM_STMT_PREPARE');
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
