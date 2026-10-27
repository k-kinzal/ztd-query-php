<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DeferredCall::class)]
#[Medium]
final class DeferredCallTest extends TestCase
{
    public function testDeriveScalarKeepsItsConstraintUntilInvocation(): void
    {
        $value = new DeferredCall(new Name('ABS'), [new DeferredCallArgument(new Row([new NumberLiteral('2'), new NumberLiteral('3')]))]);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsKeepsTheOperandBeforeTheList(): void
    {
        $value = new DeferredCall(new Name('ABS'), [new DeferredCallArgument(new Row([new NumberLiteral('2'), new NumberLiteral('3')]))]);
        self::assertSame([$value->arguments[0]->expression], $value->operands());
    }

    public function testProblemPreservesTheExpectedRowWidth(): void
    {
        $value = new DeferredCall(new Name('ABS'), [new DeferredCallArgument(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))]);
        $problem1 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem1);
        self::assertSame([1, 2], [$problem1->expected, $problem1->actual]);
    }

    public function testRenderRoundTripsTheDeferredProgramRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF ABS((2,3)) THEN DO 0; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->body);
        self::assertInstanceOf(DeferredCall::class, $operation->body->branches[0]->condition);
        self::assertSame('CREATE PROCEDURE p() IF ABS((2, 3)) THEN DO 0; END IF', $operation->toString());
    }

    public function testNamedRetainsAnExplicitArgumentAlias(): void
    {
        $value = new DeferredCall(new Name('f'), [new DeferredCallArgument(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new Name('pair'))]);
        self::assertTrue($value->named());
    }

    public function testNativeResolvesAnUnqualifiedBuiltin(): void
    {
        $value = new DeferredCall(new Name('ABS'), [new DeferredCallArgument(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))]);
        self::assertTrue($value->native(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testKeywordNameRetainsStoredNameCollisions(): void
    {
        $value = new DeferredCall(new Name('COLLATION'), [new DeferredCallArgument(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))]);
        self::assertTrue($value->keywordName(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testDeriveScalarReportsAnIdentityBeforeItsArgumentDependencies(): void
    {
        $value = new DeferredCall(new Name(''), []);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertEquals([$value->problem()], $derivation->facts()->diagnostics);
        self::assertSame([], $value->operands());
    }

    public function testRenderRetainsAnUnreferencedInvalidIdentity(): void
    {
        $sql = 'WITH c AS (SELECT ``(ABS())) SELECT 7';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $operation->toString());
        self::assertSame([], $operation->diagnostics());
    }

}
