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
final class IndexParserPartitionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $statements = ['SET NAMES utf8mb4', 'CREATE DATABASE d'];
        foreach (['t', 'd.t', 'absent.t'] as $target) {
            foreach (['', ' PARTITION BY HASH(USER())', ' PARTITION BY HASH(@x)', ' PARTITION BY HASH(ABS())'] as $partition) {
                $statements[] = "CREATE TABLE $target (a TEXT,FULLTEXT(a) WITH PARSER zz)$partition";
            }
        }
        $statements[] = 'USE d';
        foreach (['zz', 'ngram', 'NGraM'] as $parser) {
            foreach (['a NATIONAL VARCHAR(30),FULLTEXT(a)', 'FULLTEXT(a),a NATIONAL VARCHAR(30)'] as $definition) {
                $definition = str_replace('FULLTEXT(a)', "FULLTEXT(a) WITH PARSER $parser", $definition);
                $statements[] = "CREATE TABLE t ($definition) PARTITION BY HASH(USER())";
            }
        }
        $statements[] = 'CREATE PROCEDURE p() CREATE TABLE t(a TEXT,FULLTEXT(a) WITH PARSER zz) PARTITION BY HASH(USER())';
        $statements[] = 'CREATE TABLE t(a TEXT,FULLTEXT(a) WITH PARSER zz,';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Parser dependencies, catalog context, warnings and partition conditions
     */
    #[DataProvider('providerInputs')]
    public function testParserDependenciesKeepTheirPositionAmongDefinitionConditions(string $release, array $statements): void
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
