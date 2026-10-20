<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use JsonException;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RetainedPluginInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When the retained fuzz fixture is invalid
     */
    public static function providerStatements(): iterable
    {
        /** @var list<array{sql: string}> $fixture */
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/retain-plugin-change.json'), true, 512, JSON_THROW_ON_ERROR);
        $statements = ['SET NAMES utf8mb4',
            "ALTER USER IF EXISTS missing IDENTIFIED WITH UNTIL BY 'x' RETAIN CURRENT PASSWORD",
            "ALTER USER IF EXISTS missing IDENTIFIED WITH sha256_password BY 'x' RETAIN CURRENT PASSWORD",
            "CREATE USER u IDENTIFIED BY 'first'",
            "ALTER USER u IDENTIFIED WITH UNTIL BY 'x' RETAIN CURRENT PASSWORD",
            "ALTER USER u IDENTIFIED WITH UNTIL BY '' RETAIN CURRENT PASSWORD",
            "ALTER USER u IDENTIFIED WITH UNTIL BY 'x' REPLACE 'wrong' RETAIN CURRENT PASSWORD",
            "ALTER USER u IDENTIFIED WITH sha256_password AS 'bad' RETAIN CURRENT PASSWORD",
            "ALTER USER u IDENTIFIED WITH CACHING_SHA2_PASSWORD BY 'next' RETAIN CURRENT PASSWORD",
            "ALTER USER u IDENTIFIED WITH UNTIL BY 'x'", 'SELECT 1',
            $fixture[0]['sql'], 'SELECT 1', "ALTER USER CURRENT_USER IDENTIFIED BY 'root'", 'SELECT 1'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete authentication changes across missing, populated and duplicate recipients
     */
    #[DataProvider('providerStatements')]
    public function testRetentionChecksExistingPluginIdentityBeforeLoadingAReplacement(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
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
