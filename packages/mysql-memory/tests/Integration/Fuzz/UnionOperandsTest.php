<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UnionOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t (a INT, b BIGINT, c SMALLINT)', 'INSERT INTO t VALUES (1, 2, 3), (4, 5, 6)'];
            foreach (['SELECT 1', '(SELECT 1)', '((SELECT 1))', '(SELECT *)', '(SELECT a FROM t)', '(SELECT missing FROM t)'] as $first) {
                foreach (['SELECT 2', '(SELECT 2)', 'SELECT *', 'SELECT a FROM t', 'SELECT missing FROM t'] as $last) {
                    $statements[] = $first . ' UNION ' . $last;
                }
            }
            foreach (['1', 'NULL', 'a', 'b', 'c'] as $left) {
                foreach (['1', 'NULL', 'a', 'b', 'c'] as $right) {
                    $statements[] = 'SELECT ' . $left . ' AS n FROM t UNION SELECT ' . $right . ' FROM t';
                }
            }
            foreach (['SELECT 1', '(SELECT 1)'] as $first) {
                foreach (['SELECT 2', '(SELECT 2)'] as $second) {
                    foreach (['SELECT 3', '(SELECT 3)'] as $third) {
                        $statements[] = $first . ' UNION ' . $second . ' UNION ' . $third;
                    }
                }
            }
            array_push($statements, '(SELECT 1) UNION SELECT 2;', '(SELECT 1) UNION SELECT 2 ORDER BY 1', 'SELECT * FROM ((SELECT 1) UNION SELECT 2) q', 'CREATE VIEW v AS (SELECT 1) UNION SELECT 2', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN (SELECT 1) UNION SELECT 2; END IF; END');
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements UNION enclosures, field promotion and input refusal boundaries
     */
    #[DataProvider('providerReleases')]
    public function testUnionEnclosuresAndIntegerOutputFollowTheNativeRelease(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
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
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerLegacyNames(): iterable
    {
        $choices = new Choices('union-enclosures-state-20261017');
        foreach (['5.6.51', '5.7.44'] as $release) {
            $names = [];
            for ($trial = 0; $trial < 10; $trial++) {
                $names[] = 't' . $choices->integer(0, 100000);
            }
            yield $release => [$release, $names];
        }
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerLegacyNames')]
    public function testTheSameUnionRetainsItsReleaseConditionAcrossTableStates(string $release, array $names): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d');
            $observations = array_map(static function (string $name) use ($session, $native): array {
                $left = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('a')))], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name($name))));
                $right = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
                $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery($left), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $right));
                $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
                return array_map(static function (array $history) use ($statement, $sql, $session, $native): array {
                    array_map(static fn (string $setup): mysqli_result|bool => $native->query($setup), $history);
                    array_map($session->query(...), $history);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $statement)];
                }, [[], ['CREATE TABLE ' . $name . '(a INT)', 'INSERT INTO ' . $name . ' VALUES(1),(2)'], ['DROP TABLE ' . $name]]);
            }, $names);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame(array_merge(...array_fill(0, count($names), $release === '5.6.51' ? [1064, 1064, 1064] : [1146, null, 1146])), array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $flat));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
