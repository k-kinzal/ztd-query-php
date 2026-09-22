<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Type\CommonStorage;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Derives common type and NULL facts from alternative expression operands.
 * @visibility SqlSemantics
 */
final class AlternativeFacts
{
    /**
     * @param non-empty-list<Expression> $alternatives Values that can occupy one result position
     */
    public static function of(Dialect $dialect, array $alternatives): ExpressionFacts
    {
        $types = [];
        $unresolved = false;
        $nulls = [];
        foreach ($alternatives as $value) {
            $nulls[$value->nullability->value] = $value->nullability;
            if ($value->type->name !== 'unknown') {
                $types[] = $value->type;
            } elseif (!$value instanceof Literal && !$value instanceof Parameter) {
                $unresolved = true;
            }
        }
        $type = $unresolved ? TypeDescriptor::builtin($dialect, 'unknown') : CommonStorage::resolve($dialect, $types);
        $nullable = count($nulls) === 1 ? array_values($nulls)[0] : (isset($nulls[Nullability::Unknown->value]) ? Nullability::Unknown : Nullability::MaybeNull);
        return new ExpressionFacts($type, $nullable, []);
    }
    /**
     * Untyped PostgreSQL literals participate in the enclosing set's common type.
     */
    public static function setInput(Expression $value): Expression
    {
        return $value instanceof \SqlSemantics\Model\Scalar\Operator\CastExpression && $value->spelling() === 'implicit' && $value->inputs()[0]->type->name === 'unknown' ? $value->inputs()[0] : $value;
    }
}
