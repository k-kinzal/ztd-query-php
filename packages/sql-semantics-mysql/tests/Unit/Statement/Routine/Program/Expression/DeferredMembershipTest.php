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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership;

#[CoversClass(DeferredMembership::class)]
#[Medium]
final class DeferredMembershipTest extends TestCase
{
    public function testDeriveScalarKeepsItsConstraintUntilInvocation(): void
    {
        $value = new DeferredMembership(new NumberLiteral('1'), [new Row([new NumberLiteral('2'), new NumberLiteral('3')])]);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsKeepsTheOperandBeforeTheList(): void
    {
        $value = new DeferredMembership(new NumberLiteral('1'), [new Row([new NumberLiteral('2'), new NumberLiteral('3')])]);
        self::assertSame([$value->operand, ...$value->elements], $value->operands());
    }

    public function testProblemPreservesTheExpectedRowWidth(): void
    {
        $value = new DeferredMembership(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), [new NumberLiteral('3')]);
        self::assertSame([2, 1], [$value->problem()->expected, $value->problem()->actual]);
    }

    public function testRenderRoundTripsTheDeferredProgramRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() IF 1 IN ((2,3)) THEN DO 0; END IF');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement::class, $operation->statement->body);
        self::assertInstanceOf(DeferredMembership::class, $operation->statement->body->branches[0]->condition);
        self::assertSame('CREATE PROCEDURE p() IF 1 IN ((2, 3)) THEN DO 0; END IF', $operation->toString());
    }
}
