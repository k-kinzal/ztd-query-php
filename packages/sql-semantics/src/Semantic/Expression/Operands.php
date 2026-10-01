<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Binding\ExpressionRules;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;
use SqlSemantics\Semantic\Type\UnknownReason;

/**
 * Shared scalar rules; transient evaluation values never enter a statement.
 * @visibility SqlSemantics
 */
final class Operands
{
    /**
     * Asserts that an expression belongs to the requested dialect and scope.
     */
    public static function check(Scope $scope, ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf $value): void
    {
        assert(!$value instanceof Literal || $value->dialect === $scope->dialect, 'A literal must use the scope dialect.');
        assert($value instanceof Literal || $value instanceof Parameter || $value->scope === $scope, 'An expression must belong to its evaluation scope.');
    }

    /**
     * @param non-empty-list<ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf> $values
     * @return array{TypeDescriptor|Undetermined|InvalidReference, Nullability}
     */
    public static function facts(Scope $scope, string $operation, array $values, bool $call = false): array
    {
        $unknown = null;
        $inputs = [];
        foreach ($values as $value) {
            self::check($scope, $value);
            if ($value->type instanceof InvalidReference) {
                return [$value->type, Nullability::Unknown];
            }
            if ($value->type instanceof Undetermined && $value->type->reason !== UnknownReason::NullLiteral) {
                $unknown = $value->type;
            }
            $type = $value->type instanceof TypeDescriptor ? $value->type : new TypeDescriptor($scope->dialect, 'unknown');
            $inputs[] = new Expression($value instanceof Literal ? ExpressionKind::Literal : ExpressionKind::Parameter, $type, $value instanceof Parameter ? Nullability::Unknown : $value->nullability, null, symbol: $value instanceof Literal ? $value->toString() : null);
        }
        if ($unknown !== null) {
            if (!$call && in_array($operation, ['=', '<>', '<', '>', '<=', '>=', 'AND', 'OR', 'NOT', 'IS', '<=>', 'IS NULL', 'IS NOT NULL'], true)) {
                $nullability = in_array($operation, ['IS', '<=>', 'IS NULL', 'IS NOT NULL'], true) ? Nullability::NotNull : \SqlSemantics\Core\Binding\NullFacts::strict($inputs);
                return [$scope->dialect->platform()->types()->boolean(), $nullability];
            }
            return [$unknown, Nullability::Unknown];
        }
        $rules = new ExpressionRules($scope->dialect);
        $result = $call ? $rules->call($operation, $inputs, null) : $rules->operator($operation, $inputs, null);
        return [$result->type->name === 'unknown' ? new Undetermined(UnknownReason::NullLiteral) : $result->type, $result->nullability];
    }
    /**
     * Resolves a literal's result-column type using the dialect projection rules.
     */
    public static function projectedType(Literal $literal): TypeDescriptor|Undetermined
    {
        if ($literal->type instanceof TypeDescriptor) {
            return $literal->type;
        }
        $input = new Expression(ExpressionKind::Literal, new TypeDescriptor($literal->dialect, 'unknown'), $literal->nullability, null);
        $type = $literal->dialect->platform()->types()->project($input)->type;
        return $type->name === 'unknown' ? $literal->type : $type;
    }
}
