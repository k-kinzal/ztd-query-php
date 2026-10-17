<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AverageDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testAverageMetadataAndValuesAcrossPrecisionSettings(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $operands = ['NULL', 'IF(1,NULL,NULL)', 'CAST(NULL AS CHAR)', 'CAST(NULL AS DECIMAL)', '0', '1.5', '1e0', '1.23e2', "'1.23'", "_utf8mb4'1.23'", 'CAST(1 AS DECIMAL(65,30))'];
                $statements = array_merge(...array_map(static fn (int $increment): array => ['SET div_precision_increment=' . $increment, ...array_map(static fn (string $operand): string => 'SELECT AVG(' . $operand . ') AS v, SUM(' . $operand . ') AS s', $operands), 'SELECT AVG(DISTINCT NULL) AS v', 'SELECT AVG(NULL) AS v FROM (SELECT 1 AS a UNION ALL SELECT 2) x GROUP BY a', 'SELECT AVG(NULL)+1 AS v', 'SELECT CONCAT(AVG(NULL)) AS v', 'SELECT COERCIBILITY(AVG(1)) AS a, COERCIBILITY(SUM(1)) AS s, COERCIBILITY(COUNT(*)) AS c, COERCIBILITY(MIN(1)) AS m', 'SELECT CONCAT(AVG(1)) AS a, CONCAT(SUM(1)) AS s', 'SELECT * FROM (SELECT AVG(NULL) AS v) t', 'SELECT AVG(NULL) AS v UNION ALL SELECT NULL'], [0, 4, 8, 17, 18, 30]));
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

    #[DataProvider('providerReleases')]
    public function testUnchangedStructuresFollowSettingsAndPopulatedFixtures(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $expressions = [new M\Literal\NullLiteral(), new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1.5'), new M\Literal\NumberLiteral('1e0'), new M\Literal\StringLiteral(['1.23']), new M\Name\ColumnUse(new Name('a')), new M\Name\ColumnUse(new Name('b')), new M\Name\ColumnUse(new Name('c'))];
            $queries = array_map(static fn ($expression): M\QueryRoot => new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Average, [$expression]), new Name('v'))], new M\Relation\TableReference(new QualifiedName(new Name('fixture'))))), $expressions);
            $states = [['CREATE DATABASE average_domain', 'USE average_domain'], ['CREATE TABLE fixture(a DECIMAL(8,2),b DOUBLE(8,2),c VARCHAR(8))'], ["INSERT INTO fixture VALUES(1.25,1.25,'1.25'),(2.5,2.5,'2.5'),(NULL,NULL,NULL)"]];
            $observations = array_map(static function (array $history) use ($native, $session, $queries): array {
                array_map(static function (string $sql) use ($native, $session): void {
                    $native->query($sql);
                    $session->query($sql);
                }, $history);
                return array_map(static function (int $increment) use ($native, $session, $queries): array {
                    $sql = 'SET div_precision_increment=' . $increment;
                    $native->query($sql);
                    $session->query($sql);
                    return array_map(static function (M\QueryRoot $query) use ($native, $session): array {
                        $sql = (new Reference())->sql($query, $session->semantics()->profile());
                        $session->text = 'unrelated invalid input';
                        return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $query)];
                    }, $queries);
                }, [0, 4, 8, 30]);
            }, $states);
            $flat = array_merge(...array_merge(...$observations));

            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)), $release);
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
