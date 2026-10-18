<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

#[CoversClass(ReadDependencies::class)]
#[Medium]
final class ReadDependenciesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerScopes(): iterable
    {
        yield 'unused chain' => ['WITH a AS (SELECT @x), b AS (SELECT * FROM a) SELECT 1', []];
        yield 'transitive' => ['WITH a AS (SELECT @x AS x), b AS (SELECT * FROM a) SELECT * FROM b', ['x']];
        yield 'shadowed' => ['WITH a AS (SELECT @outer AS x) SELECT * FROM (WITH a AS (SELECT @inner AS x) SELECT * FROM a) AS t', ['inner']];
        yield 'recursive' => ['WITH RECURSIVE a(x) AS (SELECT @x UNION ALL SELECT x FROM a) SELECT * FROM a', ['x']];
        yield 'qualified' => ['WITH a AS (SELECT @x) SELECT * FROM d.a', []];
        yield 'ignored write clauses' => ['INSERT INTO t VALUES ROW(@written) ORDER BY @ignored', ['written']];
        yield 'explicit' => ['WITH a AS (SELECT @x AS x) TABLE a', ['x']];
    }

    /**
     * @param list<string> $variables Inputs of reachable definitions
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerScopes')]
    public function testNodesFollowLexicalAndTransitiveDefinitions(string $sql, array $variables): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze($sql)->statement;
        $nodes = (new ReadDependencies())->nodes($statement);
        $inputs = array_values(array_filter($nodes, static fn ($node): bool => $node instanceof UserVariable));

        self::assertSame($variables, array_map(static fn (UserVariable $node): string => $node->name->value, $inputs));
    }
    public function testChildrenKeepsWrittenRowsButExcludesInactiveWriteClauses(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES ROW(@written) ORDER BY @ignored')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $statement);
        self::assertSame([$statement->into, $statement->values()], (new ReadDependencies())->children($statement));
    }

}
