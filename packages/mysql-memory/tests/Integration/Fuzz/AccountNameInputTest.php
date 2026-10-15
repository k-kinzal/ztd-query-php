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
final class AccountNameInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $accounts = [];
        foreach ([16, 17, 32, 33] as $length) {
            foreach (['u', '猫'] as $letter) {
                $accounts[] = [str_repeat($letter, $length), '%'];
            }
        }
        foreach ([60, 61, 255, 256] as $length) {
            $accounts[] = ['u', str_repeat('h', $length)];
        }
        array_push($accounts, ['u', str_repeat('猫', 85)], ['u', str_repeat('猫', 86)], ['u', 'bad@host'], ['u', "a\0@b"], [str_repeat('u', 16) . "\0tail", '%'], [' ' . str_repeat('u', 16) . ' ', '%'], [' ' . str_repeat('u', 32) . ' ', '%'], ['u', ' ' . str_repeat('h', 60) . ' '], ['aa' . str_repeat('猫', 32), '%']);
        $statements = ['SET NAMES utf8mb4'];
        foreach ($accounts as [$user, $host]) {
            $quoted = array_map(static fn (string $part): string => "'" . str_replace(['\\', "'", "\0"], ['\\\\', "''", '\\0'], $part) . "'", [$user, $host]);
            foreach ([$quoted[0], implode('@', $quoted)] as $account) {
                foreach (['PROCEDURE p() SELECT 1', 'PROCEDURE p() SELECT 1 TRAILING', 'PROCEDURE p(a INT, a INT) SELECT 1', 'FUNCTION f() RETURNS INT RETURN 1', 'EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1', 'TRIGGER t BEFORE INSERT ON absent FOR EACH ROW SET @v=1', 'VIEW v AS SELECT 1'] as $definition) {
                    $statements[] = 'CREATE DEFINER=' . $account . ' ' . $definition;
                }
                $statements[] = 'CREATE USER ' . $account;
                $statements[] = 'DROP USER ' . $account;
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $debug = in_array($release, ['8.4.7', '9.1.0'], true) ? ["SHOW PARSE_TREE DROP USER '" . str_repeat('u', 33) . "'", "SHOW PARSE_TREE DROP USER 'u'@'a@b'"] : [];
            yield $release => [$release, [...$statements, ...$debug]];
        }
    }

    /**
     * @param list<string> $statements Account boundaries and competing definition diagnostics
     */
    #[DataProvider('providerInputs')]
    public function testAccountOperandsAndRefusalOrderMatchNative(string $release, array $statements): void
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
