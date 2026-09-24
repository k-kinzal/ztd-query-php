<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\Between;
use SqlSemantics\Model\Scalar\Conditional\InList;
use SqlSemantics\Model\Scalar\Conditional\JsonMembership;
use SqlSemantics\Model\Scalar\Conditional\PatternMatch;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Scalar\Operator\Operations;
use SqlSemantics\Model\Scalar\Operator\UnaryExpression;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Operations::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OperationsTest extends TestCase
{
    /**
     * @param class-string<Expression> $class
     */
    #[TestWith(['BETWEEN', 3, Between::class, 'BETWEEN'])]
    #[TestWith(['NOT BETWEEN SYMMETRIC', 3, Between::class, 'NOT BETWEEN'])]
    #[TestWith(['IN', 3, InList::class, 'IN'])]
    #[TestWith(['NOT IN', 2, InList::class, 'NOT IN'])]
    #[TestWith(['NOT LIKE ESCAPE', 3, PatternMatch::class, 'NOT LIKE'])]
    #[TestWith(['RLIKE', 2, PatternMatch::class, 'REGEXP'])]
    #[TestWith(['-', 1, UnaryExpression::class, '-'])]
    #[TestWith(['+', 2, BinaryExpression::class, '+'])]
    public function testMakeSelectsTheOperatorFormByArity(string $operator, int $arity, string $class, string $spelling): void
    {
        $operands = array_map(static fn (int $value): Expression => Expression::literal($value, Dialect::PostgreSql), range(1, $arity));
        $expression = Operations::make($operands[0]->facts, new Node('expression', 0, []), $operator, $operands);
        self::assertInstanceOf($class, $expression);
        self::assertSame($spelling, $expression->spelling());
        self::assertSame($operands, $expression->inputs());
    }

    public function testMakeBuildsJsonMembershipForMySql(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $array = Expression::literal('[1]', Dialect::MySql);
        $expression = Operations::make($value->facts, new Node('expression', 0, []), 'MEMBER OF', [$value, $array]);
        self::assertInstanceOf(JsonMembership::class, $expression);
        self::assertSame($value, $expression->value);
        self::assertSame($array, $expression->array);
    }

    #[TestWith(['BETWEEN', 2])]
    #[TestWith(['LIKE ESCAPE', 2])]
    #[TestWith(['??', 2])]
    #[TestWith(['IN', 0])]
    public function testMakeRejectsAnUnclassifiedOperatorOrArity(string $operator, int $arity): void
    {
        $facts = Expression::literal(1, Dialect::PostgreSql);
        $operands = array_map(static fn (int $value): Expression => Expression::literal($value, Dialect::PostgreSql), $arity === 0 ? [] : range(1, $arity));
        $this->expectException(InvalidStructure::class);
        Operations::make($facts->facts, new Node('expression', 0, []), $operator, $operands);
    }
}
