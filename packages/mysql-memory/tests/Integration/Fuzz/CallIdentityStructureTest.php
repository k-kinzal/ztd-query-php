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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CallIdentityStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\QueryRoot>, list<int|null>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('routine-identity-20261018');
        $names = array_map(static fn (int $trial): Name => new Name('f' . str_repeat((string) $trial, $choices->one([1, 31, 62, 63]))), range(0, 9));
        $statements = array_map(static function (Name $name): \SqlSemantics\Platform\MySql\Statement\QueryRoot {
            $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall($name, [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new ColumnUse(new Name('value')))]);
            $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call, new Name('v'))], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))));
            return new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query);
        }, $names);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, [1305, null, 1305]];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\QueryRoot> $statements The same call structures before routine creation, while present and after removal
     * @param list<int|null> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameFunctionStructuresFollowPopulatedRoutineHistories(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE routine_domain');
                $native->select_db('routine_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE routine_domain; USE routine_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $native->query('CREATE TABLE t(value INT)');
                $native->query('INSERT INTO t VALUES (1), (2), (3)');
                $session->run('CREATE TABLE t(value INT); INSERT INTO t VALUES (1), (2), (3)');
                $observed = array_map(static function (string $phase) use ($native, $session, $statements): array {
                    array_map(static function (\SqlSemantics\Platform\MySql\Statement\QueryRoot $node) use ($phase, $native, $session): void {
                        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $node->query);
                        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $node->query->items[0]);
                        $call = $node->query->items[0]->expression;
                        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\FunctionCall::class, $call);
                        $sql = match ($phase) {
                            'create' => 'CREATE FUNCTION ' . $call->name->value . '(v INT) RETURNS INT DETERMINISTIC RETURN v + 7',
                            'drop' => 'DROP FUNCTION ' . $call->name->value,
                            default => 'DO 0',
                        };
                        $native->query($sql);
                        $session->run($sql);
                    }, $statements);
                    return array_map(static function (\SqlSemantics\Platform\MySql\Statement\QueryRoot $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['absent', 'create', 'drop']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
