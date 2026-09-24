<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Inspection\Reports;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Inspection\RowWindow;

/**
 * Reads the LIMIT window of diagnostics and log event listings.
 * @visibility SqlSemantics
 */
final class RowWindows
{
    /**
     * A comma window lists the offset first; an OFFSET window lists the count first. Null when no LIMIT was written.
     */
    public static function read(Node $form, QueryContext $context): ?RowWindow
    {
        $clause = Tree::child($form, ['opt_limit_clause', 'opt_limit_clause_init']);
        if ($clause === null) {
            return null;
        }
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $options = array_map(static fn (Node $option): Expression => Reports::option($option, $scope), Tree::outer($clause, ['limit_option']));
        $first = $options[0] ?? Tree::invalid($clause, 'row window');
        if (!isset($options[1])) {
            return new RowWindow($first);
        }
        return str_contains(Tree::text($clause), ',') ? new RowWindow($options[1], $first) : new RowWindow($first, $options[1]);
    }
}
