<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DefinerPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        $groups = ['preparation' => ["CREATE DEFINER='absent'@'%' VIEW v AS SELECT 1 AS a", "CREATE DEFINER='absent'@'%' VIEW missing.v AS SELECT 1 AS a", 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1)', "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT *", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT *", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT *", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT b FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT b FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT b FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT b FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT b FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT b FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT a FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT a FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT a FROM t", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT 1 AS a", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT 1 AS a", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT 1 AS a", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT * FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT * FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT * FROM missing", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT 1 AS a,2 AS a", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW missing.v AS SELECT 1 AS a,2 AS a", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW t AS SELECT 1 AS a,2 AS a", "CREATE DEFINER='absent'@'%' EVENT e ON SCHEDULE AT 'bad' DO SET @a=1", "CREATE DEFINER='absent'@'%' EVENT e ON SCHEDULE AT '2030-01-01' DO SET @a=1", "CREATE DEFINER='absent'@'%' EVENT e ON SCHEDULE AT (SELECT a FROM t) DO SET @a=1", "ALTER DEFINER='absent'@'%' EVENT e ON SCHEDULE AT 'bad' DO SET @a=1", "ALTER DEFINER='absent'@'%' EVENT e ON SCHEDULE AT '2030-01-01' DO SET @a=1", "ALTER DEFINER='absent'@'%' EVENT e ON SCHEDULE AT (SELECT a FROM t) DO SET @a=1", "CREATE USER 'absent'@'%'", "CREATE OR REPLACE DEFINER='absent'@'%' VIEW v AS SELECT *", "CREATE DEFINER='absent'@'%' EVENT e2 ON SCHEDULE AT 'bad' DO SET @a=1"], 'references' => ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', "CREATE DEFINER='absent'@'%' PROCEDURE p0() SET @a=1", "CREATE DEFINER='absent'@'%' FUNCTION f0() RETURNS INT DETERMINISTIC RETURN 1", "CREATE DEFINER='absent'@'%' VIEW v0 AS SELECT 1 AS a", "CREATE DEFINER='absent'@'%' EVENT e0 ON SCHEDULE EVERY 1 DAY DISABLE DO SET @a=1", "CREATE DEFINER='absent'@'%' PROCEDURE p1() SET @a=1", "CREATE DEFINER='absent'@'%' FUNCTION f1() RETURNS INT DETERMINISTIC RETURN 1", "CREATE DEFINER='absent'@'%' VIEW v1 AS SELECT 1 AS a", "CREATE DEFINER='absent'@'%' EVENT e1 ON SCHEDULE EVERY 1 DAY DISABLE DO SET @a=1", "CREATE DEFINER='absent'@'%' TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET NEW.a=1", "CREATE USER 'absent'@'%'", "CREATE USER IF NOT EXISTS 'absent'@'%'", "DROP USER 'absent'@'%'", "CREATE USER 'absent'@'other'", "CREATE USER 'absent'@'%'"]];
        $groups['view identity'] = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)', 'CREATE VIEW v(x,y) AS SELECT a,a+1 FROM t', 'SELECT * FROM v', 'SELECT * FROM v alias', 'CREATE ALGORITHM=TEMPTABLE VIEW vt(x,y) AS SELECT a,a+1 FROM t', 'SELECT * FROM vt', 'SELECT * FROM vt alias', 'CREATE VIEW va(x) AS SELECT SUM(a) FROM t', 'SELECT * FROM va', 'CREATE VIEW vc(x) AS SELECT 1', 'SELECT * FROM vc', 'CREATE VIEW vv(x,y) AS SELECT x,y FROM v', 'SELECT * FROM vv', 'SELECT * FROM (SELECT * FROM v) q'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ($groups as $kind => $statements) {
                yield $release . ': ' . $kind => [$release, $statements];
            }
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerSql')]
    public function testDefinitionPreparationAndAccountReferencesPreserveConditionOrder(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
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
     * @return iterable<string, array{string, list<CreateView>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('definer-view-history-20261017');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $name = new QualifiedName(new Name('v' . $trial), new Name('d'));
                $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new Name('a')))], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'), new Name('d'))));
                $definer = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('owner' . $trial . '_' . $choices->integer(1, 999)));
                $structures[] = new CreateView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query, definer: $definer, security: \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Invoker), true);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateView> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedViewsRetainDefinerHistoriesAndPopulatedResults(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            array_map(static function (string $sql) use ($native, $session): void {
                $native->query($sql);
                $session->query($sql);
            }, ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(3),(5)']);
            $observations = array_map(static function (CreateView $definition) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($definition, $session->semantics()->profile());
                $definer = $definition->definition->definer;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\AccountName::class, $definer);
                $account = "'" . $definer->username() . "'@'%'";
                $read = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference($definition->definition->name)));
                $readSql = (new \Fuzz\Structure\Reference())->sql($read, $session->semantics()->profile());
                return array_merge(...array_map(static function (string $change) use ($native, $session, $definition, $sql, $read, $readSql): array {
                    $native->query($change);
                    $session->query($change);
                    $session->text = 'unrelated invalid input';
                    $creation = [(new Native())->observe($native, $sql), (new Memory())->observe($session, $definition)];
                    $selected = [(new Native())->observe($native, $readSql), (new Memory())->observe($session, $read)];
                    self::assertArrayNotHasKey('error', $creation[0]);
                    self::assertSame([['1'], ['3'], ['5']], $selected[0]['results'][0]['rows']);
                    return [$creation, $selected];
                }, ['SET @unrelated=1', 'CREATE USER ' . $account, 'DROP USER ' . $account]));
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
