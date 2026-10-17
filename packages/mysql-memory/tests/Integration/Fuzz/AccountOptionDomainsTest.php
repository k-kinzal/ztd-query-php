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
final class AccountOptionDomainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $original = <<<'SQL'
CREATE USER IF NOT EXISTS CURRENT_USER ( ) IDENTIFIED WITH 'text' INITIAL AUTHENTICATION IDENTIFIED BY RANDOM PASSWORD , CURRENT_USER ( ) DEFAULT ROLE `"0'\Z\``猫'猫\\\éa` REQUIRE NONE PASSWORD_LOCK_TIME UNBOUNDED FAILED_LOGIN_ATTEMPTS 000070905434
SQL;
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $numbers = ['0000', '0001', '00032767', '00032768', '00065535', '00065536', '000070905434', '2147483648', '4294967295', '4294967296', '4295000064', '18446744073709551615', '18446744073709551616', '9999999999999999999999999999999999999999999999', '0x8000', "X'8000'", '1.0', '1e0', '0x10000000000000000', "X'010000000000000000'", '0x10000000000000001', '0x1ffffffffffffffff', '0x00000000000000000000000000000000001', '00000000000000000000000000000000001', '000018446744073709551615', '000018446744073709551616'];
            $options = ['PASSWORD EXPIRE INTERVAL %s DAY', ...($release === '5.7.44' ? [] : ['FAILED_LOGIN_ATTEMPTS %s', 'PASSWORD_LOCK_TIME %s', 'PASSWORD HISTORY %s', 'PASSWORD REUSE INTERVAL %s DAY'])];
            $statements = $release === '5.7.44' ? [] : [$original];
            foreach ($options as $option) {
                foreach ($numbers as $number) {
                    $statements[] = 'CREATE USER u' . count($statements) . ' ' . sprintf($option, $number);
                }
            }
            array_push($statements, 'CREATE DATABASE account_domain', 'USE account_domain');
            foreach (['CREATE USER v', 'ALTER USER v', 'CREATE PROCEDURE p() CREATE USER v', 'CREATE PROCEDURE p() BEGIN IF 0 THEN CREATE USER v'] as $prefix) {
                $statements[] = $prefix . ' PASSWORD EXPIRE INTERVAL 0000 DAY' . (str_contains($prefix, 'BEGIN') ? '; END IF; END' : '');
            }
            array_push($statements, "CREATE USER v REQUIRE ISSUER 'a' ISSUER 'b' PASSWORD EXPIRE INTERVAL 0 DAY", "CREATE USER 'v'@'bad@host' PASSWORD EXPIRE INTERVAL 0 DAY", 'CREATE USER v PASSWORD EXPIRE INTERVAL 0 DAY PASSWORD EXPIRE INTERVAL 1.0 DAY', 'CREATE USER v PASSWORD EXPIRE INTERVAL 1.0 DAY PASSWORD EXPIRE INTERVAL 0 DAY', 'CREATE USER v PASSWORD EXPIRE INTERVAL 0000 DAY BROKEN');
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testAccountDomainsAndRefusalOrderingMatchNative(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

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
