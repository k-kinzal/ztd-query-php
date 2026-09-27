<?php

declare(strict_types=1);

namespace Deriver\Source\Validation;

use Deriver\Source\Compilation\Control\DestructuringLowering;
use Override;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Rejects destructuring forms that PHP diagnoses before program execution.
 * @visibility root
 */
final class AssignmentPatterns extends NodeVisitorAbstract
{
    /**
     * @param Collecting $errors Captured source diagnostics
     */
    public function __construct(public readonly Collecting $errors)
    {
    }

    /**
     * Checks assignment patterns and the source of reference destructuring.
     * @param Node $node Parsed source node
     * @return null Continue traversal to report independent invalid patterns
     */
    #[Override]
    public function enterNode(Node $node)
    {
        if ($node instanceof Expr\List_) {
            foreach ($this->pattern($node) as $message) {
                $this->errors->handleError(new Error($message, $node->getAttributes()));
            }
        }
        if ($node instanceof Expr\Assign && DestructuringLowering::references($node->var)) {
            $message = $this->sourceError($node->expr);
            if ($message !== '') {
                $this->errors->handleError(new Error($message, $node->getAttributes()));
            }
        }
        return null;
    }

    /**
     * Checks key consistency, nesting style, and forbidden spread entries.
     * @param Expr\List_|Expr\Array_ $pattern One level of an assignment pattern
     * @return list<string> Compile-time violations
     */
    public function pattern(Expr\List_|Expr\Array_ $pattern): array
    {
        $keyed = null;
        $empty = false;
        $errors = [];
        foreach ($pattern->items as $item) {
            if ($item === null) {
                $empty = true;
                continue;
            }
            $hasKey = $item->key !== null;
            if ($keyed !== null && $keyed !== $hasKey) {
                $errors[] = 'Cannot mix keyed and unkeyed array entries in assignments.';
            }
            $keyed = $hasKey;
            if ($item->unpack) {
                $errors[] = 'Spread operator is not supported in assignments.';
            }
            if (($item->value instanceof Expr\List_ || $item->value instanceof Expr\Array_) && $pattern->getAttribute('kind') !== $item->value->getAttribute('kind')) {
                $errors[] = 'Cannot mix [] and list().';
            }
            if ($item->value instanceof Expr\Array_) {
                array_push($errors, ...$this->pattern($item->value));
            }
        }
        if ($keyed === null) {
            $errors[] = 'Cannot use empty list.';
        } elseif ($keyed && $empty) {
            $errors[] = 'Cannot use empty array entries in keyed array assignment.';
        }
        return $errors;
    }

    /**
     * Checks writable source syntax while preserving PHP's compile-time failure category.
     * @param Expr $source Assignment source expression
     * @return string Target error, or empty for a referenceable expression
     */
    public function sourceError(Expr $source): string
    {
        if (!$source instanceof Expr\Variable && !$source instanceof Expr\ArrayDimFetch && !$source instanceof Expr\PropertyFetch && !$source instanceof Expr\StaticPropertyFetch && !$source instanceof Expr\FuncCall && !$source instanceof Expr\MethodCall && !$source instanceof Expr\StaticCall && !$source instanceof Expr\NullsafePropertyFetch && !$source instanceof Expr\NullsafeMethodCall) {
            return 'Cannot assign reference to non referenceable value.';
        }
        while ($source instanceof Expr\ArrayDimFetch || $source instanceof Expr\PropertyFetch || $source instanceof Expr\MethodCall) {
            $source = $source->var;
        }
        if ($source instanceof Expr\NullsafePropertyFetch || $source instanceof Expr\NullsafeMethodCall) {
            return 'Cannot take reference of a nullsafe chain.';
        }
        return $source instanceof Expr\New_ || $source instanceof Expr\Array_ || $source instanceof Expr\ConstFetch || $source instanceof Node\Scalar ? 'Cannot use temporary expression in write context.' : '';
    }
}
