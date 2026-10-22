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
final class AggregateNestingStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            "SELECT GROUP_CONCAT(TRUE, RANK() OVER () IS FALSE SEPARATOR '1')",
            'SELECT GROUP_CONCAT(COUNT(*))',
            "SELECT JSON_OBJECTAGG('k', JSON_OBJECTAGG('j',1))",
            "SELECT JSON_OBJECTAGG('k', SUM(1)) OVER ()",
            "SELECT JSON_OBJECTAGG('k', SUM(1)) OVER (ORDER BY 1)",
            'SELECT JSON_OBJECTAGG(RANK() OVER (), missing)',
            'SELECT JSON_OBJECTAGG(missing, RANK() OVER ())',
            "SELECT JSON_OBJECTAGG('k', (SELECT SUM(1)))",

            'SELECT GROUP_CONCAT((SELECT COUNT(*)))',
            'SELECT GROUP_CONCAT(RANK() OVER ()) OVER ()',
            'SELECT GROUP_CONCAT(RANK() OVER (), missing)',
            'SELECT GROUP_CONCAT(missing, RANK() OVER ())',
            'CREATE DATABASE nesting_domain',
            'USE nesting_domain',
            'CREATE PROCEDURE p() SELECT GROUP_CONCAT(RANK() OVER ())',
            'CALL p()',
            "CREATE PROCEDURE j() SELECT JSON_OBJECTAGG('k', SUM(1))",
            'CALL j()',

            'CREATE PROCEDURE ignored() BEGIN IF 0 THEN SELECT GROUP_CONCAT(RANK() OVER ()); END IF; END',
            'CALL ignored()',
            'CREATE PROCEDURE caught() BEGIN DECLARE CONTINUE HANDLER FOR 3593 SET @caught = 1; SELECT GROUP_CONCAT(RANK() OVER ()); END',
            'CALL caught()',
            'SELECT @caught',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Aggregate nesting and stored invocation conditions
     */
    #[DataProvider('providerReleases')]
    public function testAggregateNestingPreservesPreparationAndInvocationConditions(string $release, array $statements): void
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
