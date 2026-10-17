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
final class DiagnosticsNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['SET NAMES utf8mb4'];
        foreach (['', str_repeat('a', 64), str_repeat('a', 65), str_repeat('é', 64), str_repeat('é', 65), str_repeat('界', 64), str_repeat('界', 65)] as $name) {
            $variable = "@'" . $name . "'";
            array_push(
                $statements,
                'DO 1',
                'GET DIAGNOSTICS ' . $variable . ' = NUMBER',
                'SET @before = -5, @after = -5',
                'GET DIAGNOSTICS @before = NUMBER, ' . $variable . ' = ROW_COUNT, @after = NUMBER',
                'SELECT @before, @after',
                'SELECT ' . $variable,
                'SET ' . $variable . ' = 7',
                'SELECT ' . $variable,
            );
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The variable assignment and diagnostics history to compare
     */
    #[DataProvider('providerReleases')]
    public function testNamesAndPartialDiagnosticAssignmentsMatchNative(string $release, array $statements): void
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
