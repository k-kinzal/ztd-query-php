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
final class GrantColumnDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $inputs = ['GRANT SELECT(a) ON PROCEDURE d.p TO u', 'GRANT SELECT(a) ON FUNCTION d.p TO u', 'GRANT INSERT(a) ON PROCEDURE d.* TO u', "GRANT\nSELECT(a)\nON FUNCTION d.p TO u", 'GRANT SELECT(a) ON FUNCTION d.p TO u;', 'GRANT SELECT(a) ON FUNCTION d.p TO u /* after */', 'GRANT SELECT(a) ON FUNCTION d.p TO u AS CURRENT_USER', 'CREATE PROCEDURE p() GRANT SELECT(a) ON FUNCTION d.p TO u', 'CREATE PROCEDURE p() BEGIN GRANT SELECT(a) ON FUNCTION d.p TO u; SET @a = !1; END', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN GRANT SELECT(a) ON FUNCTION d.p TO u; END IF; END', 'GRANT SELECT(a) ON PROCEDURE d.p TO u AS CURRENT_USER WITH ROLE no_role'];
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE USER u', ...$inputs];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Routine-kind refusals, including multiline, trailing trivia and nested instructions
     */
    #[DataProvider('providerStatements')]
    public function testColumnPrivilegeRefusalPreservesNativeInputPosition(string $release, array $statements): void
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
