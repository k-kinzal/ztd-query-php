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
final class StoredReadQueriesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSqlPreservesStoredReadsAndCursorOpening(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $queries = ['VALUES ROW()', '(VALUES ROW())', 'VALUES ROW(1),ROW()', 'VALUES ROW(),ROW(1)', 'VALUES ROW(DEFAULT)', 'VALUES ROW(1,2),ROW(3)', 'VALUES ROW(1) UNION VALUES ROW(1,2)', 'SELECT 1 UNION VALUES ROW()', 'SELECT missing UNION VALUES ROW()', 'VALUES ROW() UNION SELECT missing', 'SELECT *', 'SELECT * FROM DUAL', 'SELECT * WHERE @@nonexistingvariable', 'SELECT * WHERE missingfunc()', 'SELECT EXISTS(SELECT *)', 'SELECT (SELECT *)', 'WITH c AS(VALUES ROW()) SELECT 1', 'WITH c AS(VALUES ROW()) SELECT * FROM c'];
                $statements = ['CREATE DATABASE read_domain', 'USE read_domain', ...array_merge(...array_map(static fn (string $sql, int $index): array => [$sql, 'CREATE PROCEDURE p' . $index . '(run_it INT) BEGIN IF run_it THEN ' . $sql . '; END IF; END', 'CALL p' . $index . '(0)', 'CALL p' . $index . '(1)', 'CALL p' . $index . '(0)', 'CREATE PROCEDURE c' . $index . '(run_it INT) BEGIN DECLARE cur CURSOR FOR ' . $sql . '; IF run_it THEN OPEN cur; CLOSE cur; END IF; END', 'CALL c' . $index . '(0)', 'CALL c' . $index . '(1)', 'CALL c' . $index . '(0)'], $queries, array_keys($queries))), 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC BEGIN IF 0 THEN SELECT *; END IF; RETURN 1; END', 'CREATE FUNCTION g() RETURNS INT DETERMINISTIC BEGIN IF 0 THEN VALUES ROW(); END IF; RETURN 1; END'];
                array_push($statements, ...array_merge(...array_map(static fn (string $target, int $index): array => ['CREATE FUNCTION into' . $index . '(run_it INT) RETURNS INT DETERMINISTIC BEGIN DECLARE x INT; IF run_it THEN SELECT * INTO ' . $target . '; END IF; RETURN 1; END', 'SELECT into' . $index . '(0)', 'SELECT into' . $index . '(1)', 'SELECT into' . $index . '(0)'], ['x', '@x'], [0, 1])));
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
    public function testUnchangedDefinitionsKeepSkippedAndReachedQueryDependencies(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $empty = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
            $other = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference(new QualifiedName(new Name('fixture'))));
            $queries = [$empty, new M\Query\ParenthesizedQuery(new M\Query\ValuesQuery([new M\Query\ValueRow([])])), new M\Query\Set\SetOperation(new M\Query\ValuesQuery([new M\Query\ValueRow([])]), M\Query\Set\SetOperator::Union, null, $other)];
            $stored = array_map(static fn ($query): M\Routine\Program\Selection\DeferredQuery => new M\Routine\Program\Selection\DeferredQuery($query), [...$queries, new M\Query\Set\SetOperation($other, M\Query\Set\SetOperator::Union, null, $empty), new M\Query\Select([], [new M\Query\Star()])]);
            $bodies = array_merge(...array_map(static fn ($query): array => [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$query])]), new M\Routine\Program\Block([new M\Routine\Program\Cursor\CursorDeclaration(new Name('cur'), $query)], [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [new M\Routine\Program\Cursor\OpenCursor(new Name('cur')), new M\Routine\Program\Cursor\CloseCursor(new Name('cur'))])])])], $stored));
            $definitions = array_map(static fn ($body, int $index): M\Routine\CreateProcedure => new M\Routine\CreateProcedure(new QualifiedName(new Name('typed' . $index)), new M\Routine\ParameterList([]), $body), $bodies, array_keys($bodies));
            array_map(static function (string $sql) use ($native, $session): void {
                $native->query($sql);
                $session->query($sql);
            }, ['CREATE DATABASE read_domain', 'USE read_domain']);
            $created = array_map(static function (M\Routine\CreateProcedure $definition) use ($native, $session): array {
                $session->text = 'unrelated invalid input';
                return [(new Native())->observe($native, (new Reference())->sql($definition, $session->semantics()->profile())), (new Memory())->observe($session, $definition)];
            }, $definitions);
            self::assertSame(array_map(serialize(...), array_column($created, 0)), array_map(serialize(...), array_column($created, 1)));
            $calls = array_map(static fn (M\Routine\CreateProcedure $definition): M\Dml\ProcedureCall => new M\Dml\ProcedureCall($definition->name), $definitions);
            $observations = array_map(static function (string $history) use ($native, $session, $calls): array {
                $native->query($history);
                $session->query($history);
                return array_map(static function (M\Dml\ProcedureCall $call) use ($native, $session): array {
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile())), (new Memory())->observe($session, $call)];
                }, $calls);
            }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE fixture(a INT)', 'INSERT INTO fixture VALUES(1),(2)', 'SET @run=0']);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
