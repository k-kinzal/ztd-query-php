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
final class RevokeHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $cases = [
            'REVOKE SELECT ON *.* FROM missing',
            'REVOKE USAGE ON *.* FROM missing',
            'REVOKE USAGE ON revoke_probe.* FROM empty_user',
            'REVOKE USAGE ON t FROM empty_user',
            'REVOKE USAGE ON PROCEDURE p FROM empty_user',
            'REVOKE ALL ON *.* FROM missing, u',
            'REVOKE ALL ON t FROM missing, empty_user, empty_user',
            'REVOKE ALL ON t FROM empty_user, missing',
            'REVOKE ALL ON t FROM u, missing',
            'REVOKE ALL ON t FROM missing, u',
            'REVOKE SELECT ON t FROM u, u',
            'REVOKE SELECT ON revoke_probe.* FROM missing, empty_user, empty_user',
            'REVOKE ALL ON PROCEDURE p FROM missing, empty_user, empty_user',
            'REVOKE RELOAD ON revoke_probe.* FROM missing, u, missing',
            'REVOKE RELOAD ON revoke_probe.* FROM u, missing, u',
            'REVOKE ALL ON t FROM missing@localhost, u@localhost, missing@localhost',
            'REVOKE ALL ON *.* FROM u',
        ];
        $modern = [
            'REVOKE IF EXISTS SELECT ON *.* FROM missing',
            'REVOKE SELECT ON *.* FROM missing IGNORE UNKNOWN USER',
            'REVOKE IF EXISTS ALL ON t FROM missing, empty_user, empty_user',
            'REVOKE IF EXISTS ALL ON t FROM empty_user, missing, empty_user',
            'REVOKE ALL ON t FROM missing, empty_user, empty_user IGNORE UNKNOWN USER',
            'REVOKE ALL ON t FROM empty_user, missing, empty_user IGNORE UNKNOWN USER',
            'REVOKE IF EXISTS ALL ON t FROM missing, empty_user, empty_user IGNORE UNKNOWN USER',
            'REVOKE FOO_BAR ON *.* FROM u, u',
            'REVOKE FOO_BAR ON *.* FROM u, missing, u',
            'REVOKE FOO_BAR ON *.* FROM missing, u, missing',
            'REVOKE FOO_BAR ON *.* FROM missing, u, missing IGNORE UNKNOWN USER',
            'REVOKE IF EXISTS FOO_BAR ON revoke_probe.* FROM u, u',
            'REVOKE IF EXISTS FOO_BAR ON revoke_probe.* FROM missing, u, missing',
            'REVOKE ALL ON *.* FROM missing IGNORE UNKNOWN USER',
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $legacy = str_starts_with($release, '5.');
            $history = ['CREATE DATABASE revoke_probe', 'USE revoke_probe', 'CREATE USER u', 'CREATE USER empty_user', 'CREATE TABLE t (a INT)'];
            foreach ([...$cases, ...($legacy ? [] : $modern)] as $sql) {
                array_push($history, 'GRANT SELECT ON t TO u', 'GRANT INSERT ON *.* TO u WITH GRANT OPTION');
                if (!$legacy) {
                    $history[] = 'GRANT BACKUP_ADMIN ON *.* TO u';
                }
                array_push($history, $sql, 'SHOW GRANTS FOR u');
            }
            array_push($history, 'SET max_error_count = 1', 'REVOKE SELECT ON *.* FROM missing, missing, missing', 'SHOW COUNT(*) ERRORS', 'SHOW COUNT(*) WARNINGS');
            array_push(
                $history,
                'CREATE USER source_user, target_user',
                'GRANT SELECT ON revoke_probe.* TO source_user',
                'REVOKE USAGE ON revoke_probe.* FROM target_user',
                'SHOW GRANTS FOR target_user',
                'DROP USER target_user',
                'CREATE USER target_user',
                'FLUSH TABLES',
                'REVOKE USAGE ON revoke_probe.* FROM target_user',
                'SHOW GRANTS FOR target_user',
                'GRANT RELOAD ON revoke_probe.* TO source_user',
                'REVOKE USAGE ON revoke_probe.* FROM target_user',
                'SHOW GRANTS FOR target_user',
            );
            array_push($history, 'SET max_error_count = 64', 'SET NAMES utf8mb4', 'CREATE PROCEDURE MiXeD() SELECT 1');
            foreach (['PROCEDURE', 'FUNCTION'] as $kind) {
                foreach (['MiXeD', 'missingNAME', 'éÀÇ'] as $name) {
                    foreach ($legacy ? [''] : ['', 'IF EXISTS '] as $modifier) {
                        $history[] = 'REVOKE ' . $modifier . 'ALL ON ' . $kind . ' ' . $name . ' FROM empty_user, CURRENT_USER(), empty_user';
                    }
                }
            }
            array_push($history, 'GRANT EXECUTE ON PROCEDURE MiXeD TO u', 'REVOKE ALTER ROUTINE ON PROCEDURE MiXeD FROM u', 'REVOKE EXECUTE ON PROCEDURE MiXeD FROM u', 'REVOKE EXECUTE ON PROCEDURE MiXeD FROM u');
            yield $release => [$release, $history];
        }
    }

    /**
     * @param list<string> $history The statements and grant-state observations to compare
     */
    #[DataProvider('providerHistories')]
    public function testRevokePreservesNativeDiagnosticsAndPrivilegeState(string $release, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $history);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $history);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
