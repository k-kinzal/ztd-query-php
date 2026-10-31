<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryScopes;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(QueryScopes::class)]
#[Medium]
final class QueryScopesTest extends TestCase
{
    public function testOfIncludesReachedDefinitionsWithTheirEarlierLexicalScope(): void
    {
        $definition = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $definition)]);
        $body = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))));
        $query = new M\Query\QueryExpression($with, $body);
        self::assertEquals([[$query, [], false], [$definition, [], false], [$body, ['c' => [new Name('a')]], false]], (new QueryScopes())->of($query));
    }

    public function testWalkKeepsUnusedDefinitionsOutOfTheRead(): void
    {
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [new Name('a')], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]))]);
        $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]);
        $query = new M\Query\QueryExpression($with, $body);
        self::assertInstanceOf(M\Query\SelectExpression::class, $body->items[0]);
        self::assertEquals([[$query, [], false], [$body, ['c' => [new Name('a')]], false], [$body->items[0], ['c' => [new Name('a')]], false], [$body->items[0]->expression, ['c' => [new Name('a')]], false]], (new QueryScopes())->walk($query, [], []));
    }
    public function testNodesRetainsTheScopeOfAReachedRenameDefinition(): void
    {
        $one = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]);
        $two = new M\Query\With\CommonTableExpression(new Name('d'), [new Name('x'), new Name('y')], new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
        $query = new M\Query\QueryExpression(new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $one), $two]), new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('d')))));
        $nodes = (new QueryScopes())->nodes($query);
        self::assertContainsEquals([$two, ['c' => [new Name('a')]], false], $nodes);
        self::assertContainsEquals([$two->query, ['c' => [new Name('a')]], false], $nodes);
    }

    public function testInputsRetainsAnEnclosingWriteCommonTableScope(): void
    {
        $definition = new M\Query\With\CommonTableExpression(new Name('c'), [new Name('x')], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $table = new M\Relation\TableReference(new QualifiedName(new Name('c')));
        $nodes = (new QueryScopes())->inputs([$table], new M\Query\With\With(false, [$definition]));
        self::assertContainsEquals([$definition, [], false], $nodes);
        self::assertContainsEquals([$table, ['c' => [new Name('x')]], false], $nodes);
    }

    public function testBindingsPreservesRecursiveAnchorColumnsWithoutVisitingUnusedBodies(): void
    {
        $definition = new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]));
        self::assertEquals([[], ['c' => [new Name('a')]]], (new QueryScopes())->bindings(new M\Query\With\With(true, [$definition]), [], []));
    }

    public function testProjectionStopsExistenceAtTheSelectBlock(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()]);
        $exists = new M\Expression\Subquery\Exists($query);
        $scopes = new QueryScopes();
        self::assertTrue($scopes->projection($exists, false));
        self::assertTrue($scopes->projection(new M\Query\ParenthesizedQuery($query), true));
        self::assertFalse($scopes->projection($query, true));
        self::assertEquals([[$query, [], true]], $scopes->of($exists));
    }

}
