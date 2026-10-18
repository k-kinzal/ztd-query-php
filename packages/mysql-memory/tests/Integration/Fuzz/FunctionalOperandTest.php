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
final class FunctionalOperandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE existing(a INT)'];
        foreach (['PRIMARY KEY', 'KEY k'] as $key) {
            foreach (["CAST(1 AT TIME ZONE 'text' AS DATETIME)", "CAST(missing AT TIME ZONE 'text' AS DATETIME)", "CAST(NULL AT TIME ZONE 'text' AS DATETIME)", "CAST(a AT TIME ZONE 'text' AS DATETIME)", '(1,2)', '(SELECT 1,2)', '(SELECT missing FROM absent)'] as $value) {
                foreach (['CREATE TABLE d.t', 'CREATE TABLE absent.t', 'CREATE TABLE existing', 'CREATE TABLE IF NOT EXISTS existing'] as $prefix) {
                    $statements[] = $prefix . '(a INT,' . $key . ' ((' . $value . ')))';
                }
                $statements[] = 'ALTER TABLE existing ADD ' . $key . ' ((' . $value . '))';
                $statements[] = 'ALTER TABLE absent ADD ' . $key . ' ((' . $value . '))';
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Destination lookup, index kinds, and deferred functional operands
     */
    #[DataProvider('providerInputs')]
    public function testFunctionalOperandsRetainTheirIndexAndDestinationRoles(string $release, array $statements): void
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
