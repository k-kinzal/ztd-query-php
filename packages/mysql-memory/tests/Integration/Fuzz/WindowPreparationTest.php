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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WindowPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'SET @n=0', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER ()', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER () FROM t', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER () FROM missing', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p0() BEGIN IF @run THEN SELECT FIRST_VALUE(1) IGNORE NULLS OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p0()', 'SET @run=1', 'CALL p0()', 'SET @run=0', 'CALL p0()', 'SELECT FIRST_VALUE(a) IGNORE NULLS OVER ()', 'SELECT FIRST_VALUE(a) IGNORE NULLS OVER () FROM t', 'SELECT FIRST_VALUE(a) IGNORE NULLS OVER () FROM missing', 'SELECT FIRST_VALUE(a) IGNORE NULLS OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p1() BEGIN IF @run THEN SELECT FIRST_VALUE(a) IGNORE NULLS OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p1()', 'SET @run=1', 'CALL p1()', 'SET @run=0', 'CALL p1()', 'SELECT FIRST_VALUE((SELECT a FROM missing)) IGNORE NULLS OVER ()', 'SELECT FIRST_VALUE((SELECT a FROM missing)) IGNORE NULLS OVER () FROM t', 'SELECT FIRST_VALUE((SELECT a FROM missing)) IGNORE NULLS OVER () FROM missing', 'SELECT FIRST_VALUE((SELECT a FROM missing)) IGNORE NULLS OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p2() BEGIN IF @run THEN SELECT FIRST_VALUE((SELECT a FROM missing)) IGNORE NULLS OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p2()', 'SET @run=1', 'CALL p2()', 'SET @run=0', 'CALL p2()', 'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER ()', 'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM t', 'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM missing', 'SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p3() BEGIN IF @run THEN SELECT FIRST_VALUE((1,2)) IGNORE NULLS OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p3()', 'SET @run=1', 'CALL p3()', 'SET @run=0', 'CALL p3()', 'SELECT NTH_VALUE(1,1) FROM LAST OVER ()', 'SELECT NTH_VALUE(1,1) FROM LAST OVER () FROM t', 'SELECT NTH_VALUE(1,1) FROM LAST OVER () FROM missing', 'SELECT NTH_VALUE(1,1) FROM LAST OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p4() BEGIN IF @run THEN SELECT NTH_VALUE(1,1) FROM LAST OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p4()', 'SET @run=1', 'CALL p4()', 'SET @run=0', 'CALL p4()', 'SELECT NTH_VALUE(1,0) FROM LAST OVER ()', 'SELECT NTH_VALUE(1,0) FROM LAST OVER () FROM t', 'SELECT NTH_VALUE(1,0) FROM LAST OVER () FROM missing', 'SELECT NTH_VALUE(1,0) FROM LAST OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p5() BEGIN IF @run THEN SELECT NTH_VALUE(1,0) FROM LAST OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p5()', 'SET @run=1', 'CALL p5()', 'SET @run=0', 'CALL p5()', 'SELECT NTILE(0) OVER ()', 'SELECT NTILE(0) OVER () FROM t', 'SELECT NTILE(0) OVER () FROM missing', 'SELECT NTILE(0) OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p6() BEGIN IF @run THEN SELECT NTILE(0) OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p6()', 'SET @run=1', 'CALL p6()', 'SET @run=0', 'CALL p6()', 'SELECT NTILE(@n) OVER ()', 'SELECT NTILE(@n) OVER () FROM t', 'SELECT NTILE(@n) OVER () FROM missing', 'SELECT NTILE(@n) OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p7() BEGIN IF @run THEN SELECT NTILE(@n) OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p7()', 'SET @run=1', 'CALL p7()', 'SET @run=0', 'CALL p7()', 'SELECT LEAD(1,0) OVER ()', 'SELECT LEAD(1,0) OVER () FROM t', 'SELECT LEAD(1,0) OVER () FROM missing', 'SELECT LEAD(1,0) OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p8() BEGIN IF @run THEN SELECT LEAD(1,0) OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p8()', 'SET @run=1', 'CALL p8()', 'SET @run=0', 'CALL p8()', 'SELECT NTH_VALUE(1,0) OVER ()', 'SELECT NTH_VALUE(1,0) OVER () FROM t', 'SELECT NTH_VALUE(1,0) OVER () FROM missing', 'SELECT NTH_VALUE(1,0) OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p9() BEGIN IF @run THEN SELECT NTH_VALUE(1,0) OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p9()', 'SET @run=1', 'CALL p9()', 'SET @run=0', 'CALL p9()', 'SELECT ROW_NUMBER() OVER (GROUPS CURRENT ROW)', 'SELECT ROW_NUMBER() OVER (GROUPS CURRENT ROW) FROM t', 'SELECT ROW_NUMBER() OVER (GROUPS CURRENT ROW) FROM missing', 'SELECT ROW_NUMBER() OVER (GROUPS CURRENT ROW) FROM t WHERE FALSE', 'CREATE PROCEDURE p10() BEGIN IF @run THEN SELECT ROW_NUMBER() OVER (GROUPS CURRENT ROW) FROM t; END IF; END', 'SET @run=0', 'CALL p10()', 'SET @run=1', 'CALL p10()', 'SET @run=0', 'CALL p10()', 'SELECT ROW_NUMBER() OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW)', 'SELECT ROW_NUMBER() OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t', 'SELECT ROW_NUMBER() OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM missing', 'SELECT ROW_NUMBER() OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t WHERE FALSE', 'CREATE PROCEDURE p11() BEGIN IF @run THEN SELECT ROW_NUMBER() OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t; END IF; END', 'SET @run=0', 'CALL p11()', 'SET @run=1', 'CALL p11()', 'SET @run=0', 'CALL p11()', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (GROUPS CURRENT ROW)', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (GROUPS CURRENT ROW) FROM t', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (GROUPS CURRENT ROW) FROM missing', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (GROUPS CURRENT ROW) FROM t WHERE FALSE', 'CREATE PROCEDURE p12() BEGIN IF @run THEN SELECT FIRST_VALUE(1) IGNORE NULLS OVER (GROUPS CURRENT ROW) FROM t; END IF; END', 'SET @run=0', 'CALL p12()', 'SET @run=1', 'CALL p12()', 'SET @run=0', 'CALL p12()', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW)', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM missing', 'SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t WHERE FALSE', 'CREATE PROCEDURE p13() BEGIN IF @run THEN SELECT FIRST_VALUE(1) IGNORE NULLS OVER (ROWS CURRENT ROW EXCLUDE CURRENT ROW) FROM t; END IF; END', 'SET @run=0', 'CALL p13()', 'SET @run=1', 'CALL p13()', 'SET @run=0', 'CALL p13()', 'SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER ()', 'SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER () FROM t', 'SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER () FROM missing', 'SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER () FROM t WHERE FALSE', 'CREATE PROCEDURE p14() BEGIN IF @run THEN SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER () FROM t; END IF; END', 'SET @run=0', 'CALL p14()', 'SET @run=1', 'CALL p14()', 'SET @run=0', 'CALL p14()', 'SELECT ROW_NUMBER() OVER (ORDER BY (SELECT a FROM missing) GROUPS CURRENT ROW)', 'SELECT ROW_NUMBER() OVER (ORDER BY (SELECT a FROM missing) GROUPS CURRENT ROW) FROM t', 'SELECT ROW_NUMBER() OVER (ORDER BY (SELECT a FROM missing) GROUPS CURRENT ROW) FROM missing', 'SELECT ROW_NUMBER() OVER (ORDER BY (SELECT a FROM missing) GROUPS CURRENT ROW) FROM t WHERE FALSE', 'CREATE PROCEDURE p15() BEGIN IF @run THEN SELECT ROW_NUMBER() OVER (ORDER BY (SELECT a FROM missing) GROUPS CURRENT ROW) FROM t; END IF; END', 'SET @run=0', 'CALL p15()', 'SET @run=1', 'CALL p15()', 'SET @run=0', 'CALL p15()', 'INSERT INTO t VALUES(1),(2)', 'SET @run=1', 'SET @n=1', 'CALL p0()', 'CALL p1()', 'CALL p2()', 'CALL p3()', 'CALL p4()', 'CALL p5()', 'CALL p6()', 'CALL p7()', 'CALL p8()', 'CALL p9()', 'CALL p10()', 'CALL p11()', 'CALL p12()', 'CALL p13()', 'CALL p14()', 'CALL p15()'];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testWindowArgumentsAndOptionsKeepTheirPreparationOrder(string $release, array $statements): void
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
     * @return iterable<string, array{string, list<CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('window-instruction-state-20261017');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 15; $trial++) {
                $values = array_map(static fn () => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4)));
                $row = new Row($values);
                $kind = \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::cases()[$trial % 5 + 6];
                $arguments = match ($kind) {
                    \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Lead => [$row, new UserVariable(new Name('n'))],
                    \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Lag => [new NumberLiteral('1'), new NumberLiteral((string) $choices->integer(0, 3)), $row],
                    \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::NthValue => [new NumberLiteral('1'), $row],
                    \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::FirstValue, \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::LastValue => [$row],
                };
                $expression = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction($kind, $arguments, new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(), $choices->one(\SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment::cases()));
                $instruction = new DeferredSelection([new DeferredProjection($expression)], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))));
                $structures[] = new CreateProcedure(new QualifiedName(new Name('p' . $trial)), new ParameterList([]), new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$instruction])]));
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateProcedure> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedConstructedInstructionsRetainTheirReachedConditions(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $native->query('CREATE TABLE t(a INT)');
            $native->query('INSERT INTO t VALUES(1),(2)');
            $native->query('SET @n=1');
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2); SET @n=1');
            $observations = array_map(static function (CreateProcedure $definition) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($definition, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $creation = [(new Native())->observe($native, $sql), (new Memory())->observe($session, $definition)];
                $call = new ProcedureCall($definition->name);
                $callSql = (new \Fuzz\Structure\Reference())->sql($call, $session->semantics()->profile());
                $history = array_map(static function (int $run) use ($session, $native, $call, $callSql): array {
                    $native->query('SET @run=' . $run);
                    $session->query('SET @run=' . $run);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $callSql), (new Memory())->observe($session, $call)];
                }, [0, 1, 0]);
                self::assertArrayNotHasKey('error', $creation[0]);
                self::assertSame([null, 1241, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return [$creation, ...$history];
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
