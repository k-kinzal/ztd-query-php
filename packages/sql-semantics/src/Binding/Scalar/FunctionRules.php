<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use InvalidArgumentException;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Applies registered signatures to scalar, aggregate, and window function calls.
 *
 * @visibility SqlSemantics
 */
final class FunctionRules
{
    /**
     * @param list<Expression> $operands
     * @throws InvalidArgumentException
     */
    public function bind(string $name, array $operands, Node $source, Scope $scope): Expression
    {
        if (in_array($name, ['COALESCE', 'NULLIF'], true)) {
            return (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->call($name, $operands, $source);
        }
        $signature = (new FunctionResolver())->resolve($source, $operands, $scope);
        $kind = Tree::outer($source, ['over_clause', 'windowing_clause']) !== [] ? ExpressionKind::Window : ($signature?->aggregate === true ? ExpressionKind::Aggregate : ExpressionKind::Function);
        $type = new TypeDescriptor($scope->identifiers->dialect, 'unknown');
        $nullable = Nullability::Unknown;
        if ($signature !== null) {
            $operands = $this->arguments($signature, $operands, $scope);
            $type = $signature->returnType instanceof TypeDescriptor ? $signature->returnType : ($signature->returnType)(array_map(static fn (Expression $argument): TypeDescriptor => $argument->type, $operands));
            if ($type->dialect !== $scope->identifiers->dialect) {
                throw new InvalidArgumentException('A function result must use the schema dialect.');
            }
            $nullable = $this->nullability($signature, $operands);
        }
        return new Expression($kind, $type, $nullable, $source, $operands, symbol: $name, nullExtendedBy: NullFacts::extensions($operands, $nullable));
    }

    /**
     * Records argument conversions, including inferred parameter types.
     *
     * @param list<Expression> $operands
     * @return list<Expression>
     */
    public function arguments(FunctionSignature $signature, array $operands, Scope $scope): array
    {
        $rules = new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics());
        foreach ($operands as $index => $operand) {
            $type = FunctionMatch::parameter($signature, $index);
            if ($type !== null && $type->name !== 'unknown') {
                $operands[$index] = $rules->coerce($operand, $type);
            }
        }
        return $operands;
    }

    /**

     * @param list<Expression> $operands

     */
    public function nullability(FunctionSignature $signature, array $operands): Nullability
    {
        if (!$signature->nullOnNull || $signature->nullability === Nullability::AlwaysNull) {
            return $signature->nullability;
        }
        $arguments = NullFacts::strict($operands);
        if ($arguments === Nullability::AlwaysNull || $signature->nullability === Nullability::NotNull) {
            return $arguments;
        }
        return $arguments === Nullability::Unknown ? Nullability::Unknown : $signature->nullability;
    }
}
