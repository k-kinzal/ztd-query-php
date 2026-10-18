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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class EmptyTableDefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $statements = ['CREATE TABLE t', 'CREATE TABLE missing.t', 'CREATE DATABASE d', 'USE d'];
            foreach (['', 'ENGINE=missing', 'TABLESPACE missing', '(KEY(a))', '(CHECK (TRUE))', 'ROW_FORMAT=FIXED'] as $definition) {
                foreach (['', 'IF NOT EXISTS '] as $exists) {
                    $target = 'CREATE TABLE ' . $exists . 't ' . $definition;
                    array_push($statements, 'DROP TABLE IF EXISTS t', $target, 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)', $target, 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF @run THEN ' . $target . '; END IF; END', 'SET @run=0', 'CALL p()', 'SET @run=1', 'CALL p()');
                }
            }
            array_push($statements, 'CREATE TABLE t COLLATE missing', 'CREATE TABLE missing.t ROW_FORMAT=FIXED');
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testEmptyDefinitionsPreserveVersionedPreparationOrder(string $release, array $statements): void
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
    public static function providerModernNames(): iterable
    {
        $choices = new Choices('empty-definition-state-20261017');
        foreach (['8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
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
    #[DataProvider('providerModernNames')]
    public function testTheSameEmptyConditionalDefinitionTracksTheCatalog(string $release, array $names): void
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
                $definition = new DeferredCreateTable(new QualifiedName(new Name($name)), ifNotExists: true);
                $create = new CreateProcedure(new QualifiedName(new Name('p_' . $name)), new ParameterList([]), $definition);
                $native->query((new \Fuzz\Structure\Reference())->sql($create, $session->semantics()->profile()));
                (new Memory())->observe($session, $create);
                $statement = new ProcedureCall($create->name);
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
            self::assertSame(array_merge(...array_fill(0, count($names), [4028, str_starts_with($release, '9.') ? 4028 : null, 4028])), array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $flat));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
