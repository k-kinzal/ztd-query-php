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
final class ViewDestinationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE DATABASE d', 'USE d',
            'CREATE VIEW v AS SELECT 1 INTO @a',
            'ALTER VIEW v AS SELECT 1 INTO @a',
            'CREATE VIEW v AS SELECT @b INTO @a',
            'CREATE VIEW v AS SELECT 1 INTO @a FROM DUAL WHERE @b',
            'CREATE VIEW v AS SELECT 1 FROM DUAL WHERE @b INTO @a',
            'CREATE VIEW v AS SELECT SQL_CACHE 1 INTO @a',
            'CREATE VIEW v AS SELECT SQL_CACHE SQL_NO_CACHE 1 INTO @a',
            'CREATE VIEW v AS SELECT 1 INTO @a UNION SELECT SQL_CACHE 2',
            'CREATE VIEW v AS SELECT (SELECT 1) INTO @a',
            'CREATE VIEW v AS SELECT 1 INTO missing_variable',
            "CREATE VIEW v AS SELECT 1 INTO OUTFILE '/tmp/view_rows'",
            'CREATE VIEW v AS SELECT ? INTO @a',
            'CREATE VIEW v AS SELECT 1 INTO @a WHERE ?',
            'CREATE VIEW v AS SELECT + INTO @a',
            'CREATE VIEW v AS SELECT 1 INTO @a garbage',
        ];
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The rejected view destinations and preceding conditions to compare
     */
    #[DataProvider('providerReleases')]
    public function testViewDestinationIsRefusedAtTheInputBoundary(string $release, array $statements): void
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
