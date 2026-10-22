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
        self::assertEquals([[$query, []], [$definition, []], [$body, ['c' => [new Name('a')]]]], (new QueryScopes())->of($query));
    }

    public function testWalkKeepsUnusedDefinitionsOutOfTheRead(): void
    {
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [new Name('a')], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]))]);
        $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]);
        $query = new M\Query\QueryExpression($with, $body);
        self::assertEquals([[$query, []], [$body, ['c' => [new Name('a')]]]], (new QueryScopes())->walk($query, [], []));
    }
}
