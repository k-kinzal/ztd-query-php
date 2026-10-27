<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate::class)]
#[Medium]
final class DeferredSubqueryPredicateTest extends TestCase
{
    public function testDeriveScalarPreservesProgramParameterBinding(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT x IN (SELECT 1,2)');
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveScalarKeepsLimitRestrictionsBeforeInvocation(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 IN (SELECT 1,2 LIMIT 1)');
        self::assertContains("This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'.", array_map(static fn ($problem): string => $problem->message(), $operation->diagnostics()));
    }

    public function testRenderKeepsBothCompleteOperands(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 = ALL (SELECT 2, 3)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 = ALL (SELECT 2,3)')->toString());
    }

    #[DataProvider('providerPredicates')]
    public function testOperandsKeepsTheComparedOccurrence(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate $predicate): void
    {
        self::assertSame([$predicate->operand], $predicate->operands());
    }

    #[DataProvider('providerPredicates')]
    public function testRowsDistinguishesMembershipFromScalarQuantification(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate $predicate): void
    {
        self::assertTrue($predicate->rows());
        self::assertFalse((new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate($predicate->operand, $predicate->query, \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::All))->rows());
    }

    #[DataProvider('providerPredicates')]
    public function testShapeDescribesTheFixedWidthConflict(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate $predicate): void
    {
        $shape = $predicate->shape();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $shape);
        self::assertSame([1,2], [$shape->expected,$shape->actual]);
    }

    #[DataProvider('providerPredicates')]
    public function testProblemRetainsTheOperandCondition(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate $predicate): void
    {
        self::assertSame('Operand should contain 1 column(s), not 2.', $predicate->problem()->message());
    }

    /**
     * @return iterable<array{\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate}>
     */
    public static function providerPredicates(): iterable
    {
        yield [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubqueryPredicate(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')),new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'))]))];
    }
}
