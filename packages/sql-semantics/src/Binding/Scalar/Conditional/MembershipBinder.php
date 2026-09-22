<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Conditional;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\InList;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\QueryComparison;
use SqlSemantics\Type\Nullability;

/**
 * Binds membership against scalar or record candidates without evaluating matches.
 * @visibility SqlSemantics
 */
final class MembershipBinder
{
    /**
     * @param list<Expression> $choices
     * @throws InvalidSql
     */
    public function bind(bool $negated, Expression $value, array $choices, Node $source, ExpressionRules $rules): InList
    {
        foreach ($choices as $choice) {
            if (!QueryComparison::operands($value, $choice)) {
                throw new InvalidSql(InputViolation::ComparisonWidth, $source);
            }
        }
        $types = new TypeResolution($rules->dialect, $rules->diagnostics);
        $types->common([$value, ...$choices], $source);
        $nullable = $this->nullability($value, $choices);
        return new InList(new ExpressionFacts($types->boolean(), $nullable, NullFacts::extensions([$value, ...$choices], $nullable)), $source, $value, $choices, $negated);
    }

    /**
     * An empty candidate list never returns NULL; one NULL candidate need not decide a match.
     * @param list<Expression> $choices
     */
    public function nullability(Expression $value, array $choices): Nullability
    {
        if ($choices === []) {
            return Nullability::NotNull;
        }
        if (!$value instanceof RowExpression && $value->nullability === Nullability::AlwaysNull) {
            return Nullability::AlwaysNull;
        }
        $candidateFacts = array_map(static fn (Expression $choice): string => ComparisonNullability::of($choice)->value, $choices);
        if (array_unique($candidateFacts) === [Nullability::AlwaysNull->value]) {
            return Nullability::AlwaysNull;
        }
        $facts = [ComparisonNullability::of($value)->value, ...$candidateFacts];
        if (in_array(Nullability::Unknown->value, $facts, true)) {
            return Nullability::Unknown;
        }
        return array_unique($facts) === [Nullability::NotNull->value] ? Nullability::NotNull : Nullability::MaybeNull;
    }

}
