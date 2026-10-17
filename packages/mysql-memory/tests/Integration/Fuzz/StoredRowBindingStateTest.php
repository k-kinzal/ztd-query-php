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
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StoredRowBindingStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure, list<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall>, list<int>}>
     */
    public static function providerStructures(): iterable
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('f')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $noop = new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0'))]);
        $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new QualifiedName(new Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch($row, [$noop])]));
        $statement = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(new QualifiedName(new Name('p')));
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $definition, [$statement], [1305, 1241, 1305]];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall> $statements The same calls before function creation, after creation and after removal
     * @param list<int> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameStoredRowBindsTheCurrentRoutineCatalog(string $release, \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure $definition, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE d; USE d; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $created = (new Native())->observe($native, (new Reference())->sql($definition, $session->semantics()->profile()));
                $session->text = 'unrelated invalid input';
                $constructed = (new Memory())->observe($session, $definition);
                self::assertSame($created, $constructed);
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1', 'DROP FUNCTION f']);
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
