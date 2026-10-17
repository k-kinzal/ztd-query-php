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
final class ForeignServerFieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $sql = [];
        foreach (['latin1', 'utf8mb4'] as $client) {
            $sql[] = 'SET NAMES ' . $client;
            foreach (['HOST', 'DATABASE', 'USER', 'PASSWORD', 'SOCKET', 'OWNER'] as $option) {
                foreach (['', 'abcédef', '猫', '😀', 'abc😀def', "a\0b", 'a  ', str_repeat('x', 63) . '猫', str_repeat('x', 64) . '猫', str_repeat('x', 254) . '猫', str_repeat('x', 255) . '猫', str_repeat('猫', 100)] as $value) {
                    $quoted = "'" . str_replace("\0", '\\0', $value) . "'";
                    $create = 'CREATE SERVER s FOREIGN DATA WRAPPER ' . $quoted . ' OPTIONS(' . $option . ' ' . $quoted . ')';
                    array_push($sql, $create, 'SELECT * FROM mysql.servers', $create, "ALTER SERVER s OPTIONS($option '猫é')", 'SELECT * FROM mysql.servers', 'DROP SERVER s');
                }
            }
            array_push($sql, "CREATE SERVER s FOREIGN DATA WRAPPER '😀' OPTIONS(HOST '😀', broken)", "CREATE SERVER s FOREIGN DATA WRAPPER '😀' broken", "CREATE SERVER '' FOREIGN DATA WRAPPER '😀' OPTIONS(HOST '😀')", "ALTER SERVER missing OPTIONS(HOST '😀')");
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $sql];
        }
    }

    /**
     * @param list<string> $statements Input bytes and histories that expose stored field conversions
     */
    #[DataProvider('providerHistories')]
    public function testStoredFieldsAndWarningsMatchAcrossClientCharsets(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            $observed = array_map(static function (string $sql) use ($native, $memory): array {
                return [serialize((new Native())->observe($native, $sql)), serialize((new Native())->observe($memory, $sql))];
            }, $statements);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            $native->close();
            $memory->close();
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
