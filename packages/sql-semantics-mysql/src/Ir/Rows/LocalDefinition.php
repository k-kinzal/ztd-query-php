<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

/**
 * Identifies the independent SELECT introduced by a local binding.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalDefinition
{
    /**
     * Follows the binding identified by the outer projection's facts.
     *
     * @throws InvalidConstruction When the local source is outside the independent domain
     */
    public function resolve(BoundStatement $operation, Select $body, Select|QueryExpression $statement): Select|QueryExpression
    {
        $source = $body->from;
        if ($statement === $body && $source instanceof DerivedTable) {
            Check::input(!$source->lateral, 'The local definition cannot depend on a lateral scope.');
            $query = $source->query;
        } else {
            Check::input($statement instanceof QueryExpression && $statement->orderBy === [] && $statement->limit === null
                && $source instanceof TableReference && $source->partitions === [] && $source->indexHints === [] && $source->sample === null, 'The local source requires one ordinary CTE reference.');
            $with = $statement->with;
            Check::input($with instanceof With && !$with->recursive && count($with->tables) === 1, 'The local source requires one ordinary CTE definition.');
            $definition = $with->tables[0];
            $resolution = $operation->facts->relation($source)->table;
            Check::input($resolution instanceof CommonTable && $resolution->definition === $definition, 'The local source must resolve to this CTE definition.');
            $query = $definition->query;
        }
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof Select || ($query instanceof QueryExpression && $query->with !== null), 'A local definition requires an independent SELECT.');

        return $query;
    }
}
