<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\Model\Operator;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Assigns types and NULL facts to supported scalar operations.
 *
 * @visibility SqlSemantics
 */
final class ExpressionRules
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * @param list<Expression> $operands
     * @throws SemanticException
     */
    public function call(string $name, array $operands, Node $source): Expression
    {
        if ($operands === [] || ($name === 'NULLIF' && count($operands) !== 2)) {
            throw new SemanticException('invalid-arity', 'Invalid argument count for ' . $name, $source);
        }
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if ($name === 'COALESCE') {
            $operands = $this->dialect->platform()->types()->coalesce($operands, $type);
            $nullability = NullFacts::coalesce($operands);
            return new Expression(ExpressionKind::Coalesce, $type, $nullability, $source, $operands, nullExtendedBy: NullFacts::extensions($operands, $nullability));
        }
        if ($name === 'NULLIF') {
            if (!$operands[0]->type->is($operands[1]->type->name) && !$operands[1]->type->is(Builtin::Unknown)) {
                Tree::unsupported($source, 'NULLIF overload with different input types');
            }
            $type = $operands[0]->type;
            $nullability = $operands[0]->nullability === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
            return new Expression(ExpressionKind::NullIf, $type, $nullability, $source, $operands, nullExtendedBy: NullFacts::extensions($operands, $nullability));
        }

        Tree::unsupported($source, 'function');
    }

    /**
     * Records a type-directed implicit conversion without evaluating its value.
     */
    public function coerce(Expression $operand, TypeDescriptor $type): Expression
    {
        if ($operand->type->is($type->name)) {
            return $operand;
        }

        return new Expression(ExpressionKind::Cast, $type, $operand->nullability, $operand->source, [$operand], nullExtendedBy: $operand->nullExtendedBy);
    }

    /**
     * @param non-empty-list<Expression> $operands
     */
    public function operator(Operator $operator, array $operands, Node $source): Expression
    {
        $nullability = NullFacts::strict($operands);
        $types = new TypeResolution($this->dialect);
        if ($operator->isNullTest()) {
            $type = $types->boolean();
            $nullability = Nullability::NotNull;
        } elseif ($operator->isLogical()) {
            foreach ($operands as $operand) {
                $this->predicate($operand);
            }
            $type = $types->boolean();
            $nullability = NullFacts::coalesce($operands) === Nullability::NotNull && NullFacts::strict($operands) === Nullability::NotNull ? Nullability::NotNull : Nullability::MaybeNull;
        } elseif ($operator->isComparison()) {
            $types->common($operands, $source);
            $type = $types->boolean();
            if (in_array($operator, [Operator::Is, Operator::NullSafeEqual], true)) {
                $nullability = Nullability::NotNull;
            }
        } else {
            $type = $this->arithmetic($operator, $operands, $source);
        }

        return new Expression(ExpressionKind::Operator, $type, $nullability, $source, $operands, operator: $operator, nullExtendedBy: NullFacts::extensions($operands, $nullability));
    }

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(Operator $operator, array $operands, Node $source): TypeDescriptor
    {
        return $this->dialect->platform()->types()->arithmetic($operator, $operands, $source);
    }

    /**
     * @throws SemanticException
     */
    public function predicate(Expression $expression): void
    {
        $this->dialect->platform()->types()->predicate($expression);
    }
}
