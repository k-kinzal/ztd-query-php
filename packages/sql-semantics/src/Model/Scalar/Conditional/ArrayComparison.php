<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Scalar\Query\Quantifier;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A PostgreSQL comparison of one value against every element of an array: value op ANY|SOME|ALL (array).
 * @visibility public
 * @example Reading the compared value, the operator, the quantifier and the array
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1 = ANY (ARRAY[1, 2])');
 *     $comparison = $statement->outputs[0]->expression;
 *     $comparison->operator // => \SqlSemantics\Model\Scalar\Query\ComparisonOperator::Equal
 *     $comparison->quantifier // => \SqlSemantics\Model\Scalar\Query\Quantifier::Any
 *     $comparison->spelling() // => '= ANY'
 * @example Keeping an explicitly named operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1 OPERATOR(geo.<->) ALL (ARRAY[1, 2])');
 *     $statement->outputs[0]->expression->operator->spelling() // => 'OPERATOR(geo.<->)'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT (1 OPERATOR("geo".<->) ALL (ARRAY[1, 2]))'
 */
final class ArrayComparison extends Expression
{
    /**
     * Derives a nullable boolean; a pattern operator may be negated, a comparison or named operator may not.
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly Expression $value,
        public readonly ComparisonOperator|PatternOperator|QualifiedOperator $operator,
        public readonly bool $negated,
        public readonly Quantifier $quantifier,
        public readonly Expression $array,
    ) {
        if ($value->type->dialect !== Dialect::PostgreSql || $array->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('An array comparison requires PostgreSQL operands.');
        }
        if ($operator === ComparisonOperator::NullSafeEqual || $operator instanceof PatternOperator && !in_array($operator, [PatternOperator::Like, PatternOperator::ILike], true) || $negated && !$operator instanceof PatternOperator) {
            throw new InvalidStructure('An array comparison uses a PostgreSQL comparison operator or a possibly negated LIKE or ILIKE.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $source);
    }

    /**
     * Identifies a predicate operator.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression> The compared value followed by the array
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, $this->array];
    }

    /**
     * Returns the operator and quantifier as written.
     */
    #[Override]
    public function spelling(): string
    {
        return ($this->negated ? 'NOT ' : '') . ($this->operator instanceof QualifiedOperator ? $this->operator->spelling() : $this->operator->value) . ' ' . $this->quantifier->value;
    }

    /**
     * Preserves the facts derived from the predicate.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Array comparison facts are fixed.');
        }
        return new static($this->source, $this->value, $this->operator, $this->negated, $this->quantifier, $this->array);
    }
}
