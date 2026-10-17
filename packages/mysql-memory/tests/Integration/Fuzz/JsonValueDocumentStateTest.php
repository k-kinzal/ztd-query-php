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
final class JsonValueDocumentStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\QueryRoot>, list<int|null>}>
     */
    public static function providerStructures(): iterable
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall(new ColumnUse(new Name('value')), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call)], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))));
        $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query);
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [$statement], [1146, 3146, null, 1146]];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\QueryRoot> $statements The same structures before table creation, after creation and after removal
     * @param list<int|null> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameCallTracksTheCurrentDocumentDomain(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE json_domain');
                $native->select_db('json_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE json_domain; USE json_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    if (str_starts_with($setup, 'ALTER TABLE')) {
                        $native->query("INSERT INTO t VALUES ('1')");
                        $session->run("INSERT INTO t VALUES ('1')");
                    }
                    return array_map(static function (\SqlSemantics\Platform\MySql\Statement\QueryRoot $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE TABLE t(value INT)', 'ALTER TABLE t MODIFY value VARCHAR(30)', 'DROP TABLE t']);
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
