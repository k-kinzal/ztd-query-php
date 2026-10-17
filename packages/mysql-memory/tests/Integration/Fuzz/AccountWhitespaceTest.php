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
final class AccountWhitespaceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach ([' ', "\t", "\n", "\r", "\v", "\f", "\x1f", "\x7f", "\u{a0}", "\u{3000}"] as $index => $space) {
            $name = $space . 'account' . $index . $space;
            $statements[] = "CREATE USER '" . $name . "'";
            $statements[] = "SHOW GRANTS FOR 'account" . $index . "'";
            $statements[] = "SHOW GRANTS FOR '" . $name . "'";
            $statements[] = "DROP USER '" . $name . "'";
        }
        $statements[] = "DROP USER '\r" . str_repeat('X', 80) . "'";
        $statements[] = "DROP USER 'u'@' " . str_repeat('H', 260) . " '";
        $statements[] = "DROP USER 'u'@' host '";
        $statements[] = "DROP USER ' " . str_repeat('U', 16) . " '";
        $statements[] = "DROP USER ' " . str_repeat('U', 16) . " '@'%'";
        $statements[] = "DROP USER 'u'@' " . str_repeat('H', 60) . " '";
        $statements[] = "CREATE USER '\ru\t'@' HOST '";
        $statements[] = "SHOW GRANTS FOR 'u'@'host'";
        $statements[] = "SHOW GRANTS FOR ' u '@' HOST '";
        $statements[] = "DROP USER 'u'@'host'";
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The account histories to compare
     */
    #[DataProvider('providerReleases')]
    public function testWhitespaceChangesAccountResolutionAndLengthDiagnostics(string $release, array $statements): void
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
