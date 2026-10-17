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
final class GrantLevelDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d','CREATE TABLE d.t(a INT)','CREATE USER u','CREATE PROCEDURE d.p() BEGIN END'];
            $grants = ['GRANT ALL ON PROCEDURE *.* TO u','GRANT ALL ON FUNCTION d.* TO u','GRANT SELECT ON PROCEDURE d.p TO u','GRANT EXECUTE ON d.* TO u','GRANT EXECUTE ON d.t TO missing','GRANT RELOAD ON d.* TO u','GRANT RELOAD ON d.t TO u','GRANT SELECT(a) ON d.* TO u','GRANT SELECT(a) ON *.* TO u','GRANT EXECUTE ON PROCEDURE d.p TO u','GRANT ALL ON PROCEDURE * TO u','GRANT SELECT(a) ON * TO u'];
            foreach (['*', '*.*', 'd.*', 'missing.*', 'd.t'] as $level) {
                foreach (['_sqlfaker_identifier', str_repeat('x', 40), "u@'bad@host'"] as $recipient) {
                    $grants[] = 'GRANT SELECT(a) ON ' . $level . ' TO ' . $recipient;
                }
            }
            if (!str_starts_with($release, '5.')) {
                $grants = [...$grants, 'GRANT SELECT, foo ON FUNCTION b TO u', 'GRANT foo, SELECT ON FUNCTION b TO u', 'GRANT foo ON FUNCTION b TO u', 'GRANT EXECUTE, foo ON d.t TO u', 'GRANT foo ON d.missing TO u', 'GRANT SELECT(a), foo ON d.* TO u', 'GRANT foo ON PROCEDURE *.* TO u', 'GRANT RELOAD, foo ON d.* TO u'];
                $grants = [...$grants, ...array_map(static fn (string $sql): string => $sql . ' AS CURRENT_USER', $grants)];
            }
            foreach ($grants as $index => $sql) {
                array_push($statements, $sql, 'CREATE PROCEDURE d.r' . $index . '() ' . $sql, 'CALL d.r' . $index . '()', 'CREATE PROCEDURE d.s' . $index . '() BEGIN IF FALSE THEN ' . $sql . '; END IF; END', 'CALL d.s' . $index . '()');
            }
            $prefixes = [];
            foreach (['GRANT', 'REVOKE'] as $verb) {
                foreach (['*', '*.*', 'd.*', 'd.t'] as $level) {
                    foreach (['', ' TO', ' FROM', ' TO ?', ' FROM ?', " TO u@'bad@host'", " FROM u@'bad@host'"] as $suffix) {
                        $prefixes[] = $verb . ' SELECT(a) ON ' . $level . $suffix;
                    }
                }
            }
            array_push($statements, ...$prefixes);
            array_push($statements, 'USE d', ...$grants, ...$prefixes);
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Privilege scopes, missing databases, AS precedence and unreachable program instructions
     */
    #[DataProvider('providerStatements')]
    public function testGrantTargetsPreserveNativeDefinitionAndInvocationRules(string $release, array $statements): void
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
