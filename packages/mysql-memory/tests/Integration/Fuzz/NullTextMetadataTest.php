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
final class NullTextMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testPreparedNullTextAndAccountMetadataFollowTheResultEncoding(string $release): void
    {
        $statements = [
            "CREATE USER u IDENTIFIED BY ''",
            'SET NAMES latin1', 'SHOW CREATE USER u',
            'SET NAMES utf8mb4', 'SHOW CREATE USER u',
            'SET character_set_results=NULL', 'SHOW CREATE USER u',
            'SET NAMES utf8mb4',
            'SELECT TRIM(?)', 'SELECT TRIM(NULL)', "SELECT TRIM('x' FROM ?)",
            "SELECT TRIM(? FROM 'x')", "SELECT TRIM(NULL FROM 'x')", "SELECT TRIM('x' FROM NULL)",
            'SELECT LTRIM(?), RTRIM(?), CONCAT(?)',
            "SELECT CONCAT(?, 'x'), CONCAT(NULL, 'x'), CONCAT_WS(',',NULL,'x')",
        ];
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
