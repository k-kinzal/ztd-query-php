<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\With;

use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

/**
 * Binds exact definition identities into ordinal reads of an ordinary WITH.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WithLowering
{
    /**
     * Resolves the ordered lexical definitions and the final consumer.
     */
    public function lower(BoundStatement $operation, QueryExpression $statement): WithProjection
    {
        $with = $statement->with;
        Check::input($with instanceof With && !$with->recursive && $statement->orderBy === [] && $statement->limit === null, 'WITH requires ordinary definitions and a checked SELECT body.');
        $positions = [];
        $definitions = [];
        $aliases = new LocalProjectionAliases();
        foreach ($with->tables as $position => $table) {
            $definition = $this->query($operation, $table->query, $positions);
            if ($table->columns === []) {
                $aliases->check($definition);
            }
            $definitions[] = new WithDefinition($definition);
            $positions[spl_object_id($table)] = $position;
        }
        $body = $this->query($operation, $statement->body, $positions);
        Check::input(!$body instanceof WithProjection, 'An ordinary WITH requires a SELECT body in its own scope.');

        return new WithProjection($definitions, $body, $operation->context->profile->grammar);
    }

    /**
     * Lowers one member without retaining the analysis or the declaration objects.
     * @param array<int, int> $positions Preceding declaration objects and their positions
     */
    public function query(BoundStatement $operation, Query $query, array $positions): Select|LocalProjection|WithProjection|WithRead
    {
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        if ($query instanceof QueryExpression && $query->with !== null) {
            return $this->lower($operation, $query);
        }
        Check::input($query instanceof ParsedSelect, 'Each WITH member requires a checked SELECT projection.');
        $source = $query->from;
        if ($source instanceof TableReference) {
            $relation = $operation->facts->relation($source)->table;
            if ($relation instanceof CommonTable) {
                $position = $positions[spl_object_id($relation->definition)] ?? null;
                Check::input($position !== null, 'A local CTE reference must resolve to a preceding definition in this WITH.');
                Check::input($source->partitions === [] && $source->indexHints === [] && $source->sample === null, 'A local WITH read requires an ordinary CTE reference.');
                $local = new LocalDefinition();
                $local->consumer($query);

                return new WithRead($position, $local->columns($operation, $query, $source));
            }
        }
        if ($source instanceof DerivedTable) {
            return (new LocalProjectionLowering())->lower($operation, $query);
        }

        return (new SelectLowering())->lower($operation, $query);
    }
}
