<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
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
final class QueryCountStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{M\QueryRoot, M\Routine\CreateProcedure}>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('query-count-scope-20261022');
        $structures = [];
        for ($trial = 0; $trial < 9; $trial++) {
            $count = new M\Query\Clause\ProgramVariable(new Name('n' . $trial));
            $limit = new M\Query\Clause\RowLimit($count, new M\Literal\NumberLiteral('1'), $choices->one(M\Query\Clause\OffsetSpelling::cases()));
            $table = new QualifiedName(new Name('t'), new Name('d'));
            $query = match ($trial % 3) {
                0 => new M\Query\QueryExpression(null, new M\Query\ExplicitTable($table), limit: $limit),
                1 => new M\Query\QueryExpression(null, new M\Query\ValuesQuery(array_map(static fn (string $number): M\Query\ValueRow => new M\Query\ValueRow([new M\Literal\NumberLiteral($number)]), ['1', '2', '3'])), limit: $limit),
                2 => new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference($table), limit: $limit),
            };
            $node = new M\QueryRoot($query);
            $variable = new M\Routine\Program\VariableDeclaration([$count->name], new M\Type\Integral(M\Type\Kind\IntegralKind::Int), default: new M\Literal\NumberLiteral('2'));
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Block([$variable], [$node]));
            $structures[] = [$node, $create];
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<array{M\QueryRoot, M\Routine\CreateProcedure}> $structures Counted queries retained across declaration scopes
     */
    #[DataProvider('providerStructures')]
    public function testTheSameCountedQueryRunsWithItsDeclarationAndDoesNotLeakTheBinding(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            try {
                array_map(static function (string $sql) use ($native, $session): void {
                    $native->query($sql);
                    $session->query($sql);
                }, ['CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES (1),(2),(3)']);
                $session->text = 'opaque request label';
                array_map(static function (array $pair) use ($native, $session): void {
                    [$node, $create] = $pair;
                    $steps = [$node, $create, new M\Dml\ProcedureCall($create->name), $node];
                    $history = array_map(static function (M\Statement $step) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($step, $session->semantics()->profile()));
                        self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $step)));
                        return $expected;
                    }, $steps);
                    self::assertSame([1327, null, null, 1327], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                    self::assertSame([['2'], ['3']], $history[2]['results'][0]['rows']);
                }, $structures);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
