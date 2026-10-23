<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Read;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Node;

/**
 * Identifies sampling in the reached read dependencies of UPDATE and DELETE.
 *
 * Sampling inside an assignment or predicate belongs to the containing write.
 * Unreferenced CTE definitions do not activate this condition. The caller owns
 * the write role; this rule neither checks a storage engine nor executes a read.
 * Native 9.1 observations distinguish missing tables, reached sampling and
 * skipped stored instructions. Source: https://dev.mysql.com/doc/refman/9.1/en/select.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Sampling
{
    /**
     * Answers the fixed sampling condition of a complete write or stored template.
     */
    public function of(Node $write): ?WriteMisuse
    {
        return $this->inputs([$write]);
    }

    /**
     * @param list<Node> $inputs The original input relations, assignments and clauses
     */
    public function inputs(array $inputs, ?WithClause $with = null): ?WriteMisuse
    {
        foreach ((new ReadDependencies())->inputs($inputs, $with instanceof With ? $with : null) as $node) {
            if ($node instanceof TableReference && $node->sample !== null) {
                return new WriteMisuse(WriteRule::SampledWrite);
            }
        }
        return null;
    }
}
