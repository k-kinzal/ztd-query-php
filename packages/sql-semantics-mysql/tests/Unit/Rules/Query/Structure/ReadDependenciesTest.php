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
        yield 'case-distinct' => ['WITH c AS (SELECT @lower AS a), C AS (SELECT @upper AS b) SELECT c.a,C.b FROM c,C', ['lower', 'upper']];
        yield 'case-distinct unused recursion' => ['WITH RECURSIVE c AS (TABLE c) TABLE C', []];
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
    public function testNodesCanVisitCompletedOperandsBeforeOwnersWithoutReversingSiblings(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('SELECT outer_call(first_call(), second_call())')->statement;
        $nodes = (new ReadDependencies())->nodes($statement, childrenFirst: true);
        $calls = array_values(array_filter($nodes, static fn ($node): bool => $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall));
        self::assertSame(['first_call', 'second_call', 'outer_call'], array_map(static fn ($call): string => $call->name->value, $calls));
    }

    public function testChildrenKeepsWrittenRowsButExcludesInactiveWriteClauses(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES ROW(@written) ORDER BY @ignored')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $statement);
        self::assertSame([$statement->into, $statement->values()], (new ReadDependencies())->children($statement));
    }

    public function testWithRecognizesAnUpdateScopeWithoutOpeningItsDefinitions(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT @unused) UPDATE t SET a=1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Update::class, $statement);
        self::assertSame($statement->with, (new ReadDependencies())->with($statement));
        self::assertSame([], array_values(array_filter((new ReadDependencies())->nodes($statement), static fn ($node): bool => $node instanceof UserVariable)));
    }

    public function testInputsFollowAnEnclosingCommonTableInAWrite(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT @read AS a) UPDATE t,c SET t.a=c.a')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Update::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\With::class, $statement->with);
        $nodes = (new ReadDependencies())->inputs($statement->tables, $statement->with);
        $variables = array_values(array_filter($nodes, static fn ($node): bool => $node instanceof UserVariable));
        self::assertSame(['read'], array_map(static fn (UserVariable $variable): string => $variable->name->value, $variables));
    }

    public function testDefineKeepsEarlierBindingsOutsideANonrecursiveDefinition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('WITH a AS (SELECT 1), b AS (TABLE a) SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\QueryExpression::class, $statement->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\With::class, $statement->query->with);
        $scopes = [[]];
        $definitions = [];
        $scope = (new ReadDependencies())->define($statement->query->with, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, $scopes, $definitions, 0);
        self::assertSame(['a' => 0, 'b' => 1], $scopes[$scope]);
        self::assertSame([], $scopes[$definitions[0][1]]);
        self::assertSame(['a' => 0], $scopes[$definitions[1][1]]);
    }

}
