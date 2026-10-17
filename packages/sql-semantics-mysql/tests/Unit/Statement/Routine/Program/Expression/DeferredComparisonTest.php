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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredComparison;

#[CoversClass(DeferredComparison::class)]
#[Medium]
final class DeferredComparisonTest extends TestCase
{
    public function testDeriveScalarRetainsDependenciesWithoutReportingTheInvocationConstraint(): void
    {
        $value = new DeferredComparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike(new NumberLiteral('2'), new Row([new NumberLiteral('3'), new NumberLiteral('4')])));
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
        $operation = $semantics->analyze('CREATE PROCEDURE p() KILL 1 = (1 LIKE ROW(1,2))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill::class, $operation->statement->body);
        self::assertInstanceOf(DeferredComparison::class, $operation->statement->body->process);
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
    }
    public function testOperandsKeepsTheWrittenOrder(): void
    {
        $value = new DeferredComparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike(new NumberLiteral('2'), new Row([new NumberLiteral('3'), new NumberLiteral('4')])));
        self::assertSame([$value->left, $value->right], $value->operands());
    }

    public function testProblemIdentifiesTheFirstNonScalarUse(): void
    {
        $value = new DeferredComparison(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike(new NumberLiteral('2'), new Row([new NumberLiteral('3'), new NumberLiteral('4')])));
        $problem2 = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem2);
        self::assertSame([1, 2], [$problem2->expected, $problem2->actual]);
    }
}
