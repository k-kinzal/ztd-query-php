<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * Checks fixed column constraints of reached relation inputs, including writes.
 * Unused common tables remain templates; activated definitions supply read rows.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InputColumns
{
    /**
     * Finds a contradiction in a complete statement's input dependencies.
     */
    public function of(Node $root): ?Diagnostic
    {
        return $this->inputs([$root]);
    }

    /**
     * Checks sources in their lexical WITH scope without constructing a replacement query.
     *
     * @param list<Node> $inputs Complete input occurrences
     */
    public function inputs(array $inputs, ?WithClause $with = null): ?Diagnostic
    {
        foreach ((new ReadDependencies())->inputs($inputs, $with instanceof With ? $with : null) as $node) {
            $problem = match (true) {
                $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable && ($node->on instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $node->on instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) => (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$node->on]),
                $node instanceof JsonTable => (new JsonColumns())->problem($node->columns),
                $node instanceof DerivedTable, $node instanceof CommonTableExpression => (new ReadColumns())->problem($node->query),
                default => null,
            };
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }
}
