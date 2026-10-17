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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike;

#[CoversClass(DeferredLike::class)]
#[Medium]
final class DeferredLikeTest extends TestCase
{
    public function testDeriveScalarRetainsDependenciesWithoutReportingTheInvocationConstraint(): void
    {
        $row = static fn () => new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredLike($row(), new NumberLiteral('1'));
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
        $problem1 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem1);
        self::assertSame(2, $problem1->actual);
        self::assertCount(2, $value->operands());
    }

    public function testRenderingRetainsTheStoredPredicateThroughLowering(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() KILL ROW(1,2) LIKE 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill::class, $operation->statement->body);
        self::assertInstanceOf(DeferredLike::class, $operation->statement->body->process);
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }
    public function testOperandsKeepsTheWrittenOrder(): void
    {
        $value = new DeferredLike(new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        self::assertSame([$value->operand, $value->pattern], $value->operands());
    }

    public function testProblemIdentifiesTheFirstNonScalarUse(): void
    {
        $value = new DeferredLike(new NumberLiteral('1'), new Row([new NumberLiteral('2'), new NumberLiteral('3')]));
        $problem2 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem2);
        self::assertSame([1, 2], [$problem2->expected, $problem2->actual]);
    }
}
