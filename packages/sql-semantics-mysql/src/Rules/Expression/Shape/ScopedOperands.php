<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Expression as E;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Checks operand positions after their enclosing CTE widths become known.
 * An open subquery may supply a row to comparison or membership. Other scalar
 * positions still require one column; grouping preserves the owner's role.
 * No catalog, evaluation or executable-operation classification is involved.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ScopedOperands
{
    /**
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     */
    public function problem(Node $node, array $common): ?Diagnostic
    {
        $shapes = new RowShapes();
        if ($node instanceof E\Comparison) {
            return $shapes->problem($node->left, $node->right, $common);
        }
        if ($node instanceof E\Predicate\InList) {
            foreach ($node->elements as $element) {
                $problem = $shapes->problem($node->operand, $element, $common);
                if ($problem !== null) {
                    return $problem;
                }
            }
            return null;
        }
        if ($node instanceof E\Subquery\QuantifiedComparison) {
            return $this->quantified($node, $common);
        }
        if ($node instanceof E\Subquery\InQuery) {
            return $shapes->query($node->operand, $node->query, $common);
        }
        if ($node instanceof E\Grouped || $node instanceof E\GroupedRow || $node instanceof E\Row) {
            return null;
        }
        foreach ((new ReadDependencies())->children($node) as $child) {
            if ($child instanceof Scalar) {
                $parts = $shapes->parts($child, $common);
                if ($parts !== null && $parts !== []) {
                    return new E\Problem\OperandColumns(1, count($parts), $child);
                }
            }
        }
        return null;
    }

    /**
     * Quantified equality membership permits rows; ordered comparisons require scalars.
     * @param array<string, list<Name|null>|null> $common Enclosing common-table outputs
     */
    public function quantified(E\Subquery\QuantifiedComparison $node, array $common): ?Diagnostic
    {
        $rows = $node->quantifier->comparesRows($node->operator);
        $width = (new QueryWidth())->of($node->query, common: $common);
        if (!$rows && $width !== null && $width !== 1) {
            return new E\Problem\OperandColumns(1, $width, $node);
        }
        return (new RowShapes())->query($node->operand, $node->query, $common);
    }
}
