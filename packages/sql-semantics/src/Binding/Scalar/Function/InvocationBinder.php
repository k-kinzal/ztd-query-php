<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Function;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scalar\FunctionClauses;
use SqlSemantics\Binding\Scalar\WindowBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\FunctionReference;

/**
 * Selects the invocation form from its group, row and window operands.
 * @visibility SqlSemantics
 */
final class InvocationBinder
{
    /**
     * Binds the call and its window; MySQL parses IGNORE NULLS and FROM LAST on window functions but rejects both,
     * so neither binds.
     *
     * @param list<Expression> $arguments Bound direct arguments
     * @param list<Expression> $orderedInputs Bound per-row arguments of an ordered-set aggregate
     * @throws \SqlSemantics\InvalidSql
     */
    public function bind(Node $source, Scope $scope, FunctionReference $reference, ExpressionFacts $facts, array $arguments, bool $aggregate, array $orderedInputs): Expression
    {
        $arguments = ArgumentNotations::apply($source, $arguments, $scope);
        $filterNode = FunctionClauses::find($source, ['filter_clause']);
        $filterExpression = $filterNode === null ? null : (Tree::outer($filterNode, ['a_expr', 'expr'])[0] ?? null);
        $filter = $filterExpression === null ? null : (new \SqlSemantics\Binding\ExpressionBinder())->bind($filterExpression, $scope);
        if ($filter !== null) {
            (new \SqlSemantics\Binding\ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($filter);
        }
        $text = strtoupper(Tree::text($source));
        if (FunctionClauses::allRows($source)) {
            $call = new \SqlSemantics\Model\Scalar\Function\AllRowsAggregate($facts, $source, $reference, $filter);
        } elseif (FunctionClauses::find($source, ['within_group_clause']) !== null) {
            $call = new \SqlSemantics\Model\Scalar\Function\OrderedSetCall($facts, $source, $reference, $arguments, $this->orderedSet($source, $scope, $orderedInputs), $filter);
        } elseif ($aggregate || $filter !== null || FunctionClauses::find($source, ['sum_expr']) !== null || preg_match('/^\S+\s*\(\s*(DISTINCT|ALL)\b/', $text) === 1 || Tree::hasTokens(FunctionClauses::ordering($source))) {
            $mode = preg_match('/^\S+\s*\(\s*DISTINCT\b/', $text) === 1 ? \SqlSemantics\Model\Scalar\Function\ArgumentMode::Distinct : \SqlSemantics\Model\Scalar\Function\ArgumentMode::All;
            $positions = \SqlSemantics\Model\Scalar\Function\AggregateCall::concatenation($reference, $facts) ? array_map(static fn (int $ordinal, Expression $argument): \SqlSemantics\Model\OutputColumn => new \SqlSemantics\Model\OutputColumn($ordinal, null, $argument), array_keys($arguments), $arguments) : null;
            $call = new \SqlSemantics\Model\Scalar\Function\AggregateCall($facts, $source, $reference, $arguments, $mode, (new \SqlSemantics\Binding\SelectModifiersBinder())->ordering(FunctionClauses::ordering($source), $scope, $positions), $filter, self::separator($source, $scope));
        } else {
            $call = new \SqlSemantics\Model\Scalar\Function\FunctionCall($facts, $source, $reference, $arguments);
        }
        $over = FunctionClauses::find($source, ['over_clause', 'windowing_clause']);
        if ($over !== null && $call instanceof \SqlSemantics\Model\Scalar\Function\OrderedSetCall) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::OrderedSetWindow, $source);
        }
        foreach (['opt_null_treatment' => 'IGNORE NULLS', 'opt_from_first_last' => 'FROM LAST'] as $clause => $rejected) {
            $modifier = FunctionClauses::find($source, [$clause]);
            if ($modifier !== null && strtoupper(Tree::text($modifier)) === $rejected) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::WindowModifier, $modifier);
            }
        }
        return $over === null ? $call : new \SqlSemantics\Model\Scalar\Function\WindowCall($facts, $source, $call, (new WindowBinder())->bind($over, $scope));
    }

    /**
     * Reads the explicit SEPARATOR string of a MySQL GROUP_CONCAT; null when the default comma applies.
     */
    public static function separator(Node $source, Scope $scope): ?\SqlSemantics\Model\Scalar\Value\Literal
    {
        $clause = FunctionClauses::find($source, ['opt_gconcat_separator']);
        $token = $clause === null ? null : ($clause->tokens()[1] ?? null);
        $literal = $token === null ? null : (new \SqlSemantics\Binding\LiteralBinder($scope->identifiers->dialect))->bind($token);
        return $literal instanceof \SqlSemantics\Model\Scalar\Value\Literal ? $literal : null;
    }

    /**
     * @param list<Expression> $inputs Per-row arguments after signature coercion
     * @return list<\SqlSemantics\Model\Ordering>
     * @throws LogicException
     */
    public function orderedSet(Node $source, Scope $scope, array $inputs): array
    {
        $ordering = (new \SqlSemantics\Binding\SelectModifiersBinder())->ordering(FunctionClauses::ordering($source), $scope, null);
        foreach ($ordering as $index => $order) {
            if (!isset($inputs[$index])) {
                throw new LogicException('Each ordered-set input requires its bound signature argument.');
            }
            $ordering[$index] = new \SqlSemantics\Model\Ordering($inputs[$index], $order->descending, $order->nullsFirst);
        }
        return $ordering;
    }
}
