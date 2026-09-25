<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Renders a node back to source, short enough to quote inside a finding.
 *
 * @visibility root
 */
final class NodeText
{
    /**
     * How many characters of rendered source a quoted expression keeps.
     */
    public const MAX_LENGTH = 60;

    private Standard $printer;

    /**
     * Builds a renderer over the standard pretty printer.
     */
    public function __construct()
    {
        $this->printer = new Standard();
    }

    /**
     * The node rendered on one line, truncated with an ellipsis when long.
     */
    public function render(Node $node): string
    {
        $source = $this->printer->prettyPrint([$node instanceof Node\Stmt ? $node : new Node\Stmt\Expression($this->asExpr($node))]);
        $collapsed = trim(preg_replace('/\s+/', ' ', $source) ?? $source);
        $collapsed = rtrim($collapsed, ';');

        if (strlen($collapsed) <= self::MAX_LENGTH) {
            return $collapsed;
        }

        return substr($collapsed, 0, self::MAX_LENGTH - 1) . '…';
    }

    /**
     * The node as an expression, falling back to a placeholder for anything else.
     */
    public function asExpr(Node $node): Node\Expr
    {
        return $node instanceof Node\Expr ? $node : new Node\Scalar\String_('');
    }
}
