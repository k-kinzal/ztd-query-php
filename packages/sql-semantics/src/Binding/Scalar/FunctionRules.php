<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Validation\InvalidStructure;
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
     * @throws InvalidStructure
     */
    public function bind(string $name, array $operands, Node $source, Scope $scope): Expression
    {
        if (in_array($name, ['COALESCE', 'NULLIF'], true)) {
            return (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->call($name, $operands, $source);
        }
        $orderedInputs = FunctionClauses::orderedInputs($source, $scope);
        $argumentCount = count($operands);
        $signature = (new FunctionResolver())->resolve($source, [...$operands, ...$orderedInputs], $scope);
        $kind = $signature?->aggregate === true ? ExpressionKind::Aggregate : ExpressionKind::Function;
        $type = TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown');
        $nullable = Nullability::Unknown;
        if ($signature !== null) {
            $boundArguments = $this->arguments($signature, [...$operands, ...$orderedInputs], $scope);
            $operands = array_slice($boundArguments, 0, $argumentCount);
            $orderedInputs = array_slice($boundArguments, $argumentCount);
            $type = $signature->returnType instanceof TypeDescriptor ? $signature->returnType : ($signature->returnType)(array_map(static fn (Expression $argument): TypeDescriptor => $argument->type, $boundArguments));
            if ($type->dialect !== $scope->identifiers->dialect) {
                throw new InvalidStructure('A function result must use the schema dialect.');
            }
            $nullable = $this->nullability($signature, $operands);
        }
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $nullable, NullFacts::extensions($operands, $nullable));
        $reference = $signature === null
            ? new \SqlSemantics\Model\Scalar\Function\UnresolvedFunction(new \SqlSemantics\Model\Scalar\Function\FunctionName($scope->identifiers->parts(FunctionClauses::find($source, ['func_name']) ?? new Node('function_name', 0, [$source->tokens()[0]]))))
            : new \SqlSemantics\Model\Scalar\Function\DeclaredFunction($signature);
        return (new Function\InvocationBinder())->bind($source, $scope, $reference, $facts, $operands, $kind === ExpressionKind::Aggregate, $orderedInputs);
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
