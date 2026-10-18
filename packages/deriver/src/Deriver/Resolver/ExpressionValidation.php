<?php

declare(strict_types=1);

namespace Deriver\Deriver\Resolver;

use Override;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Checks expression constraints that require interpreting literal operand types.
 * @visibility namespace
 */
final class ExpressionValidation extends NodeVisitorAbstract
{
    /**
     * @param Collecting $errors Diagnostics for the current file
     */
    public function __construct(private readonly Collecting $errors)
    {
    }

    /**
     * Rejects unpacking a literal that cannot be an array or Traversable.
     * @return null Continue traversal
     */
    #[Override]
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\ArrayItem && $node->unpack && ($node->value instanceof Node\Scalar\Int_ || $node->value instanceof Node\Scalar\Float_ || $node->value instanceof Node\Scalar\String_ || $node->value instanceof Expr\ConstFetch && in_array(strtolower($node->value->name->toString()), ['true', 'false', 'null'], true))) {
            $this->errors->handleError(new Error('Only arrays and Traversables can be unpacked.', $node->getAttributes()));
        }
        return null;
    }
}
