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
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WindowOperandDomainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)'];
            foreach (['0', '0001', '1.0', '1e0', '18446744073709551615', '18446744073709551616', '.5', '1.', '0x1'] as $count) {
                foreach (['NTILE(' . $count . ')', 'LEAD(a,' . $count . ')', 'LAG(a,' . $count . ')'] as $call) {
                    $target = 'SELECT ' . $call . ' OVER() FROM t';
                    array_push($statements, $target, 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $target . '; END IF; END');
                }
            }
            yield $release . ' operands' => [$release, $statements];
            $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT,b VARCHAR(8))', "INSERT INTO t VALUES(1,'x'),(2,'y')"];
            foreach (['ROW_NUMBER()', 'RANK()', 'DENSE_RANK()', 'NTILE(2)', 'PERCENT_RANK()', 'CUME_DIST()', 'LEAD(1,1)', 'LAG(1,1)', 'FIRST_VALUE(1)', 'LAST_VALUE(1)', 'NTH_VALUE(1,1)', 'SUM(1)', 'COUNT(*)'] as $call) {
                foreach (['', ' FROM t', ' FROM t WHERE FALSE', ' FROM t GROUP BY a', ' FROM t ORDER BY a'] as $source) {
                    array_push($statements, 'SELECT ' . $call . ' OVER()' . $source, 'SELECT ' . $call . ' OVER() AS v' . $source, 'SELECT (' . $call . ' OVER())+0 AS v' . $source);
                }
            }
            foreach (['LEAD(a,1)', 'LAG(a,1)', 'FIRST_VALUE(b)', 'LAST_VALUE(b)', 'SUM(a)'] as $call) {
                array_push($statements, 'SELECT ' . $call . ' OVER() FROM t', 'SELECT ' . $call . ' OVER() AS v FROM t');
            }
            yield $release . ' metadata' => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testWindowDomainsAgreeForLiteralBoundariesAndStoredOutputs(string $release, array $statements): void
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
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerDirectCounts(): iterable
    {
        $choices = new Choices('window-count-domain-20261017');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $counts = [];
            for ($trial = 0; $trial < 20; $trial++) {
                $counts[] = $choices->integer(1, 100);
            }
            yield $release => [$release, $counts];
        }
    }

    /**
     * @param list<int> $counts
     */
    #[DataProvider('providerDirectCounts')]
    public function testDirectCountsAgreeAcrossSessionTransitions(string $release, array $counts): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $native->query('CREATE TABLE t(a INT)');
            $native->query('INSERT INTO t VALUES(1),(2),(3)');
            $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2),(3)');
            $statement = new QueryRoot(new Select([], [new SelectExpression(new WindowFunction(WindowFunctionKind::Tile, [new UserVariable(new Name('n'))], new WindowSpec()))], new TableReference(new QualifiedName(new Name('t')))));
            $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
            $observations = array_map(static function (int $count) use ($statement, $sql, $session, $native): array {
                return array_map(static function (string $value) use ($statement, $sql, $session, $native): array {
                    $native->query('SET @n=' . $value);
                    $session->query('SET @n=' . $value);
                    $session->text = 'unrelated invalid input';
                    return [serialize((new Native())->observe($native, $sql)), serialize((new Memory())->observe($session, $statement))];
                }, ['0', (string) $count, 'NULL', '1']);
            }, $counts);
            $flat = array_merge(...$observations);
            self::assertSame(array_column($flat, 0), array_column($flat, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }

}
