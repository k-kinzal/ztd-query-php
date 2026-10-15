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
final class CheckSubqueryPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE present(a INT,b INT)'];
        foreach (['CREATE TABLE t', 'CREATE TABLE present', 'CREATE TABLE IF NOT EXISTS present', 'CREATE TABLE absent_db.t', 'CREATE TEMPORARY TABLE t'] as $prefix) {
            foreach (['1 IN(SELECT * FROM absent)', '1 IN(SELECT * FROM present)', 'EXISTS(SELECT * FROM absent)', '1 IN(SELECT unknown_column FROM present)', '1 IN(SELECT abs() FROM present)', '(1,2)=(3,4,5)', '1 IN(SELECT * FROM absent) AND (1,2)=(3,4,5)'] as $condition) {
                $statements[] = $prefix . '(a INT,CHECK(' . $condition . '))';
                $statements[] = 'DROP TABLE IF EXISTS t';
            }
        }
        $statements[] = 'CREATE TABLE t(a INT, CONSTRAINT c CHECK(1 IN(SELECT * FROM absent)), CONSTRAINT c CHECK(a>0))';
        $statements[] = 'CREATE TABLE t(a INT,FOREIGN KEY(a) REFERENCES absent(a),CHECK(1 IN(SELECT * FROM absent2)))';
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Competing query, constraint and target diagnostics
     */
    #[DataProvider('providerReleases')]
    public function testPreparationMatchesNative(string $release, array $statements): void
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
