<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable as ParsedJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;

/**
 * Binds the ordered output slots of one independently scoped derived relation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DerivedBinding
{
    private readonly DerivedSource $value;

    /**
     * Checks the nested SELECT before sharing any of its positions.
     */
    public function __construct(private readonly BoundStatement $operation, private readonly DerivedTable $input)
    {
        Check::input(!$input->lateral, 'A derived join input cannot be lateral.');
        $query = $input->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof Select, 'A derived join input requires a SELECT definition.');
        $from = JoinBinding::ungroup($query->from);
        Check::input(!$from instanceof ParsedJoin && !$from instanceof DerivedTable && !$from instanceof TableList, 'A derived join input must be independent of any joined input.');
        $definition = (new SelectLowering())->lower($operation, $query);
        if ($input->columns === []) {
            (new LocalProjectionAliases())->check($definition);
        }
        $this->value = new DerivedSource($definition);
    }

    /**
     * Returns the exact parsed occurrence used by the binding facts.
     */
    public function relation(): DerivedTable
    {
        return $this->input;
    }

    /**
     * Resolves the ordinal in the derived definition without allocating a table-catalog slot.
     */
    public function resolved(ResolvedColumn $resolution): ColumnReference|TextColumnReference
    {
        Check::input(
            $resolution->depth === 0 && $resolution->relation === $this->input,
            'A derived reference must belong to its join input.'
        );
        $slots = $this->operation->facts->relation($this->input)->shape->slots;
        $position = array_search($resolution->slot, $slots, true);
        Check::input($position !== false && isset($this->value->definition->columns[$position]), 'A derived reference must resolve to a defined field.');

        return $this->value->columnType($position) instanceof Varchar ? new TextColumnReference($position) : new ColumnReference($position);
    }

    /**
     * Returns the independent model with no binding state.
     */
    public function source(): DerivedSource
    {
        return $this->value;
    }
}
