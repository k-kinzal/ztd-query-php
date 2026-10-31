<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\ScopedOperands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ScopedOperands::class)]
#[Small]
final class ScopedOperandsTest extends TestCase
{
    public function testProblemRetainsAValueWhoseEnclosingDefinitionSuppliesTwoColumns(): void
    {
        $value = new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))));
        $rules = new ScopedOperands();
        self::assertNull($rules->problem($value, []));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, $rules->problem($value, ['c' => [new Name('a'), new Name('b')]]));
        self::assertTrue((new M\QueryRoot(new M\Query\QueryExpression(new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]))]), new M\Query\Select([], [$value]))))->hasExecutionCondition());
    }

    public function testProblemPreservesRowsInComparisonsAndMembership(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $comparison = new M\Expression\Comparison(M\Expression\ComparisonOperator::Equal, $row, new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))));
        self::assertNull((new ScopedOperands())->problem($comparison, ['c' => [new Name('a'), new Name('b')]]));
        $enclosing = new M\Query\QueryExpression(new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]))]), new M\Query\Select([], [new M\Query\SelectExpression($comparison)]));
        self::assertSame($enclosing, (new M\QueryRoot($enclosing))->query);
        $membership = new M\Expression\Predicate\InList(new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))), [new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])]);
        self::assertNull((new ScopedOperands())->problem($membership, ['c' => [new Name('a'), new Name('b')]]));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, (new ScopedOperands())->problem($membership, ['c' => [new Name('a')]]));
    }

    public function testQuantifiedChecksMembershipAndOrderedQuantificationSeparately(): void
    {
        $in = new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('1'), new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
        $ordered = new M\Expression\Subquery\QuantifiedComparison(new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))), M\Expression\ComparisonOperator::Less, M\Expression\Subquery\Quantifier::Any, new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c')))));
        $scope = ['c' => [new Name('a'), new Name('b')]];
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, (new ScopedOperands())->problem($in, $scope));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, (new ScopedOperands())->quantified($ordered, $scope));
    }

    public function testProblemLeavesGroupingAndNestedRowRolesToTheirOwner(): void
    {
        $scope = ['c' => [new Name('a'), new Name('b')]];
        $grouped = new M\Expression\Grouped(new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))));
        self::assertNull((new ScopedOperands())->problem($grouped, $scope));
        self::assertInstanceOf(M\Expression\Problem\OperandColumns::class, (new ScopedOperands())->problem(new M\Query\SelectExpression($grouped), $scope));
        self::assertNull((new ScopedOperands())->problem(new M\Expression\Row([new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('c'))))), new M\Literal\NumberLiteral('1')]), $scope));
    }

}
