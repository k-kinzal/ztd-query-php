<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;

/**
 * Identifies the independent SELECT introduced by a local binding.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
            Check::input($statement instanceof QueryExpression && $statement->options === null
                && $source instanceof TableInput && $source->sample === null && !$source->table->only, 'The local source requires one ordinary CTE reference.');
            $with = $statement->with;
            Check::input($with !== null && !$with->recursive && count($with->tables) === 1, 'The local source requires one ordinary CTE definition.');
            $definition = $with->tables[0];
            Check::input(
                $definition->materialization === null && $definition->search === null && $definition->cycle === null,
                'The local CTE admits its ordinary materialization policy.'
            );
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
