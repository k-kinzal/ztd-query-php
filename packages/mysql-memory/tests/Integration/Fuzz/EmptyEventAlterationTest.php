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
final class EmptyEventAlterationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'ALTER EVENT absent', 'ALTER DEFINER=CURRENT_USER EVENT absent',
            'ALTER EVENT absent_db.e', 'CREATE DATABASE d',
            'CREATE PROCEDURE d.p() ALTER EVENT absent',
            'CREATE FUNCTION d.f() RETURNS INT ALTER EVENT absent',
            'CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW ALTER EVENT absent',
            'CREATE EVENT d.outer_event ON SCHEDULE EVERY 1 DAY DO ALTER EVENT absent',
            'CREATE PROCEDURE d.p() BEGIN ALTER EVENT absent; BOGUS END',
            'USE d', 'ALTER EVENT absent', 'ALTER DEFINER=CURRENT_USER EVENT absent',
            'ALTER EVENT absent_db.e', 'ALTER EVENT absent BOGUS',
            'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DISABLE DO SELECT 1',
            'ALTER EVENT e', 'ALTER DEFINER=CURRENT_USER EVENT e',
            "ALTER EVENT e COMMENT 'changed'",
            'CREATE PROCEDURE p() ALTER EVENT e',
            'CREATE PROCEDURE p() BEGIN IF FALSE THEN ALTER EVENT e; END IF; END',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Attribute orders with competing rejection reasons
     */
    #[DataProvider('providerReleases')]
    public function testEmptyChangesRefuseBeforeLookupAndInsideProgramBodies(string $release, array $statements): void
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
