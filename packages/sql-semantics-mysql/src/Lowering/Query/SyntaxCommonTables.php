<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

/**
 * Retains duplicate CTE diagnostics from a completed definition list.
 *
 * The duplicate is refused after its complete query, before later input is
 * read. Each list is its own name scope, including lists in unused nested
 * queries. Names use the case-sensitive table comparison of the supported
 * server profile. Only the decoded offending name escapes lowering.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxCommonTables
{
    /**
     * Answers the first repeated name in a completed WITH list.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name !== 'with_list') {
            return null;
        }
        $seen = [];
        foreach ((new Lists())->items($node) as $item) {
            $table = $lowering->form($item);
            $name = $lowering->names->identifier($table->node(0));
            if (isset($seen[$name->value])) {
                return new ParseFailure(new Misuse(MisuseRule::DuplicateCommonTable, $name), true);
            }
            $seen[$name->value] = true;
        }

        return null;
    }
}
