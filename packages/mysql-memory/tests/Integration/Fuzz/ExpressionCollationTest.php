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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollated;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ExpressionCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['SELECT ', 'SET @x= ', 'DO ', 'KILL '] as $prefix) {
            $expressions = ['1 COLLATE missing', 'unknown() COLLATE missing', '(SELECT a FROM t) COLLATE missing', '(1,2) COLLATE missing', '1 COLLATE missing +', '1 COLLATE missing + ?'];
            if ($prefix === 'KILL ') {
                $expressions = [...$expressions, "'a' COLLATE missing", "BINARY 'a' COLLATE missing", '(SELECT 1) COLLATE missing'];
            }
            foreach ($expressions as $expression) {
                $sql = $prefix . $expression;
                $statements = [...$statements, $sql, 'CREATE PROCEDURE p() BEGIN IF FALSE THEN ' . $sql . '; END IF; END', 'DROP PROCEDURE IF EXISTS p'];
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $cases = $statements;
            foreach (['SELECT ', 'DO ', 'SET @x= ', 'KILL '] as $index => $prefix) {
                foreach (['(1,2) COLLATE binary', '(SELECT a,b FROM absent) COLLATE binary', '(1,2) COLLATE missing', '(SELECT a FROM absent) COLLATE missing'] as $part => $expression) {
                    $name = 's' . $index . '_' . $part;
                    $cases = [...$cases, 'CREATE PROCEDURE ' . $name . '() BEGIN IF @run THEN ' . $prefix . $expression . '; END IF; END', 'SET @run=0', 'CALL ' . $name . '()', 'SET @run=1', 'CALL ' . $name . '()', 'SET @run=0', 'CALL ' . $name . '()'];
                }
            }
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testCollationLookupKeepsItsReleaseAndInstructionPhase(string $release, array $statements): void
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
        $choices = new Choices('collation-instruction-state-20261017');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $values = array_map(static fn () => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4)));
                $expression = new DeferredCollated(new Row($values), new Name($choices->one(['binary', 'latin1_bin'])));
                $instruction = match ($trial % 3) {
                    0 => new DeferredSelection([new DeferredProjection($expression)]),
                    1 => new DeferredEvaluation([new DeferredProjection($expression)]),
                    2 => new DeferredKill(null, $expression),
                };
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
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d');
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
