<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

#[CoversClass(SubqueryOptions::class)]
#[Small]
final class SubqueryOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(Query): Node}>
     */
    public static function providerBoundaries(): iterable
    {
        yield 'scalar' => [static fn (Query $query): Node => new ScalarSubquery($query)];
        yield 'exists' => [static fn (Query $query): Node => new Exists($query)];
        yield 'in' => [static fn (Query $query): Node => new InQuery(new NumberLiteral('1'), $query)];
        yield 'quantified' => [static fn (Query $query): Node => new QuantifiedComparison(new NumberLiteral('1'), ComparisonOperator::Equal, Quantifier::Any, $query)];
    }

    /**
     * @param Closure(Query): Node $construct The consuming subquery constructor
     */
    #[DataProvider('providerBoundaries')]
    public function testCheckRejectsCacheOptionsAcrossParentheses(Closure $construct): void
    {
        $query = new ParenthesizedQuery(new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))]));
        $this->expectExceptionMessage('SQL_CACHE is not an option of a subquery.');

        $construct($query);
    }

    public function testCheckAllowsAnOrdinarySubquery(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $subquery = new ScalarSubquery($query);

        self::assertSame($query, $subquery->query);
    }
    public function testCheckAcceptsNestedDerivedTablesWithTheirOwnModifierDomain(): void
    {
        $derived = new DerivedTable(new Select([SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]), new \SqlSemantics\Statement\Identifier\Name('x'));
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], from: $derived);
        $subquery = new ScalarSubquery($query);

        self::assertSame($query, $subquery->query);
    }

    public function testCheckRejectsProcedureAnalyseAcrossAnExpressionSubqueryBoundary(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), procedure: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse());
        $this->expectExceptionMessage('An expression subquery cannot invoke PROCEDURE ANALYSE.');
        (new SubqueryOptions())->check($query);
    }
}
