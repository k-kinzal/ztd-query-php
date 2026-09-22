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
     * @param list<Expression> $arguments Bound direct arguments
     * @param list<Expression> $orderedInputs Bound per-row arguments of an ordered-set aggregate
     * @throws \SqlSemantics\InvalidSql
     */
    public function bind(Node $source, Scope $scope, FunctionReference $reference, ExpressionFacts $facts, array $arguments, bool $aggregate, array $orderedInputs): Expression
    {
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
            $call = new \SqlSemantics\Model\Scalar\Function\AggregateCall($facts, $source, $reference, $arguments, $mode, (new \SqlSemantics\Binding\SelectModifiersBinder())->ordering(FunctionClauses::ordering($source), $scope, null), $filter);
        } else {
            $call = new \SqlSemantics\Model\Scalar\Function\FunctionCall($facts, $source, $reference, $arguments);
        }
        $over = FunctionClauses::find($source, ['over_clause', 'windowing_clause']);
        if ($over !== null && $call instanceof \SqlSemantics\Model\Scalar\Function\OrderedSetCall) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::OrderedSetWindow, $source);
        }
        return $over === null ? $call : new \SqlSemantics\Model\Scalar\Function\WindowCall($facts, $source, $call, (new WindowBinder())->bind($over, $scope));
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
