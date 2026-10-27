<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(DeferredBody::class)]
#[Medium]
final class DeferredBodyTest extends TestCase
{
    public function testRenderRetainsAnUnusedDefinitionWithoutRaisingItsReadCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT ROW(1,2) LIKE 1) SELECT 7');
        self::assertSame('WITH c AS (SELECT ROW(1, 2) LIKE 1) SELECT 7', $operation->toString());
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveQueryKeepsEveryProjectionPositionAndItsInvalidType(): void
    {
        $body = new DeferredBody(new M\Routine\Program\DeferredSelection([
            new M\Routine\Program\DeferredProjection(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]), new Name('pair')),
            new M\Query\SelectExpression(new M\Literal\NumberLiteral('3'), new Name('v')),
        ]));
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $body->deriveQuery($derivation, $derivation->environment());
        self::assertSame(['pair', 'v'], array_map(static fn ($slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertInstanceOf(Invalid::class, $fact->shape->slots[0]->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testProblemKeepsTheDeferredOperandWidth(): void
    {
        $body = new DeferredBody(new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, $body->problem());
    }

    public function testAReachedDefinitionCannotBeConstructedAsAnOrdinaryRead(): void
    {
        $body = new DeferredBody(new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]));
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $body)]);
        $query = new M\Query\QueryExpression($with, new M\Query\ExplicitTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('c'))));
        $this->expectExceptionMessage('A read statement has coherent fixed output columns.');
        new M\QueryRoot($query);
    }
}
