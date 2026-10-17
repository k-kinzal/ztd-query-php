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
final class UnsupportedWindowTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = [
            'CREATE DATABASE d', 'USE d',
            'SELECT GROUP_CONCAT(1) OVER ()',
            'SELECT GROUP_CONCAT(missing) OVER ()',
            'SELECT GROUP_CONCAT(1) OVER () FROM absent',
            'SELECT GROUP_CONCAT(1) OVER missing',
            'SELECT GROUP_CONCAT(1) OVER () trailing garbage',
            'SELECT BINARY 1, GROUP_CONCAT(1) OVER ()',
            'SELECT GROUP_CONCAT(1) OVER (), BINARY 1',
            'SELECT GROUP_CONCAT(1) OVER (), ?',
            'SELECT COUNT(DISTINCT missing) OVER ()',
            'SELECT FIRST_VALUE(missing) IGNORE NULLS OVER ()',
            'SELECT COUNT(*) OVER (GROUPS CURRENT ROW)',
            'SELECT GROUP_CONCAT(1) OVER (), missing',
            'SELECT missing, GROUP_CONCAT(1) OVER ()',
            'SELECT GROUP_CONCAT(missing) OVER absent',
            'SELECT GROUP_CONCAT(1) OVER (absent)',
            'SELECT GROUP_CONCAT(1) OVER (ORDER BY missing)',
            'SELECT COUNT(DISTINCT 1) OVER (), missing',
            'SELECT COUNT(DISTINCT 1) OVER absent',
            'SELECT GROUP_CONCAT(1) OVER () WHERE GROUP_CONCAT(2) OVER ()',
            'SELECT (SELECT missing), GROUP_CONCAT(1) OVER ()',
            'SELECT GROUP_CONCAT(1) OVER (), (SELECT missing)',
            'SELECT GROUP_CONCAT(1) OVER (), COUNT(DISTINCT 1) OVER missing',
            'SELECT (SELECT GROUP_CONCAT(1) OVER ()), missing',
            'SELECT missing, (SELECT GROUP_CONCAT(1) OVER ())',

            'CREATE PROCEDURE d.p(IN run_body BOOLEAN) BEGIN IF run_body THEN SELECT GROUP_CONCAT(1) OVER (); END IF; END',
            'CALL d.p(FALSE)', 'CALL d.p(TRUE)',
            'CREATE PROCEDURE d.q() SELECT GROUP_CONCAT(missing) OVER ()',
            'CALL d.q()',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Window refusals, binding errors and deferred routine bodies
     */
    #[DataProvider('providerInputs')]
    public function testUnsupportedWindowsPreserveBindingAndDeferredRoutineExecution(string $release, array $statements): void
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
