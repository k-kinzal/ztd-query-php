<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DefinitionNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d'];
            foreach (['a', '.a', 't.a', 'd.t.a', 'wrong.t.a', 'd.wrong.a'] as $name) {
                foreach (['CHANGE ' . $name . ' b INT', 'CHANGE a ' . $name . ' INT', 'ADD COLUMN ' . $name . ' INT', 'DROP COLUMN ' . $name, 'ADD KEY ' . $name . '(a)', 'DROP KEY ' . $name] as $action) {
                    $statements = [...$statements, 'DROP TABLE IF EXISTS t', 'ALTER TABLE t ' . $action, 'CREATE TABLE t(a INT, KEY a(a))', 'INSERT INTO t VALUES(1),(2)', 'ALTER TABLE t ' . $action];
                }
                $statements = [...$statements, 'DROP TABLE IF EXISTS t', 'CREATE TABLE t(' . $name . ' INT)'];
            }
            $statements = [...$statements, 'DROP TABLE IF EXISTS t', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)'];
            foreach (['.a', 't.a', 'd.t.a', 'wrong.t.a', 'd.wrong.a', 'T.a', 'D.t.a'] as $name) {
                foreach (['ALTER TABLE t ADD COLUMN ' . $name . ' INT', 'ALTER TABLE t DROP COLUMN ' . $name, 'CREATE TABLE u(' . $name . ' INT)'] as $action) {
                    $statements = [...$statements, 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $action . '; END IF; END', 'CALL p()', 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN ' . $action . '; END', 'CALL p()'];
                }
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testDefinitionNamesPreserveQualifiersAndDotNotices(string $release, array $statements): void
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
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

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
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable>}>
     */
    public static function providerDirectHistories(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('definition-names-20261017');
        $statements = [];
        for ($trial = 0; $trial < 40; $trial++) {
            $column = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('c' . $choices->integer(0, 10000)), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('x')));
            $statements[] = new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn(new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition($column, new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int))))]);
        }
        yield '5.6.51' => ['5.6.51', $statements];
        yield '5.7.44' => ['5.7.44', $statements];
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable> $statements
     */
    #[DataProvider('providerDirectHistories')]
    public function testDirectDefinitionNamesMatchNativeStateTransitions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            $native->query('CREATE DATABASE d');
            $native->query('CREATE DATABASE x');
            $session->query('CREATE DATABASE d; CREATE DATABASE x');
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable $statement) use ($native, $session): array {
                $native->query('USE d');
                $session->query('USE d');
                $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $expected = [serialize((new Native())->observe($native, $sql))];
                $actual = [serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement))];
                $native->query('USE x');
                $native->query('DROP TABLE IF EXISTS t');
                $session->query('USE x; DROP TABLE IF EXISTS t');
                $session->text = 'unrelated invalid input';
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                $native->query('CREATE TABLE t(a INT)');
                $native->query('INSERT INTO t VALUES(1),(2)');
                $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2)');
                $session->text = 'unrelated invalid input';
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                return [serialize($expected), serialize($actual)];
            }, $statements);
            self::assertSame(array_column($observations, 0), array_column($observations, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }

}
