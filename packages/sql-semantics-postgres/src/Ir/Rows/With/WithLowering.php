<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;

/**
 * Binds ordinary WITH reads by declaration identity, then discards the binding context.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class WithLowering
{
    /**
     * Lowers definitions in lexical order and binds the final projection.
     */
    public function lower(BoundStatement $operation, QueryExpression $statement): WithProjection
    {
        $with = $statement->with;
        Check::input($with !== null && !$with->recursive && $statement->options === null, 'WITH needs an ordinary declaration list and a checked SELECT body.');
        $definitions = [];
        $positions = [];
        foreach ($with->tables as $index => $table) {
            Check::input($table->search === null && $table->cycle === null, 'An ordinary WITH definition has no recursive search or cycle.');
            $policy = match ($table->materialization) {
                null => WithMaterialization::Default,
                Materialization::Materialized => WithMaterialization::Materialized,
                Materialization::NotMaterialized => WithMaterialization::NotMaterialized,
            };
            $definitions[] = new WithDefinition($this->query($operation, $table->query, $positions), $policy);
            $positions[spl_object_id($table)] = $index;
        }
        $body = $this->query($operation, $statement->body, $positions);
        Check::input(!$body instanceof WithProjection, 'The ordinary WITH body requires a projected query or constant table in its own scope.');

        return new WithProjection($definitions, $body, $operation->context->profile->grammar);
    }

    /**
     * Lowers a member in the supplied scope or starts an independent nested scope.
     * @param array<int, int> $positions Definition positions by exact analysis identity
     */
    public function query(BoundStatement $operation, Query|Statement $query, array $positions): Select|LocalProjection|ValuesQuery|WithProjection|WithRead
    {
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        if ($query instanceof QueryExpression && $query->with !== null) {
            return $this->lower($operation, $query);
        }
        if ($query instanceof ValuesList) {
            return (new ValuesLowering())->lower($operation, $query);
        }
        Check::input($query instanceof ParsedSelect, 'A WITH member requires a checked SELECT projection or constant table.');
        $source = $query->from;
        if ($source instanceof TableInput) {
            $fact = $operation->facts->relation($source);
            if ($fact->table instanceof CommonTable) {
                $index = $positions[spl_object_id($fact->table->definition)] ?? null;
                Check::input($index !== null, 'The CTE reference must resolve to a visible definition in this WITH.');
                return $this->read($operation, $query, $source, $index);
            }
        }
        if ($source instanceof DerivedTable) {
            return (new LocalProjectionLowering())->lower($operation, $query);
        }

        return (new SelectLowering())->lower($operation, $query);
    }

    /**
     * Resolves each projected field of a local relation by the bound heading.
     */
    public function read(BoundStatement $operation, ParsedSelect $query, TableInput $source, int $position): WithRead
    {
        (new LocalDefinition())->consumer($query);
        Check::input($source->sample === null && !$source->table->only, 'A local WITH read requires an ordinary CTE reference.');
        $slots = $operation->facts->relation($source)->shape->slots;
        $fields = $operation->facts->query($query)->fields();
        Check::input($fields !== null, 'The local WITH read requires a resolved output heading.');
        $columns = [];
        foreach ($query->targets as $index => $target) {
            Check::input($target instanceof ExpressionTarget, 'A local WITH read needs explicit result fields.');
            $resolution = $operation->facts->scalar($target->expression)->resolution;
            Check::input(
                $resolution instanceof ResolvedColumn && $resolution->relation === $source && $resolution->depth === 0,
                'Each local WITH result must read a resolved slot in its own occurrence.'
            );
            $slot = array_search($resolution->slot, $slots, true);
            $name = $fields->at($index)->name;
            Check::input($slot !== false && $name !== null, 'A local WITH result needs a named, resolved slot.');
            $columns[] = new LocalResultColumn($slot, new Identifier($name->value));
        }

        return new WithRead($position, $columns);
    }
}
