<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join as ParsedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList;

/**
 * Binds the ordered output slots of one independently scoped derived relation.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
        Check::input(!$from instanceof ParsedJoin && !$from instanceof DerivedTable && !$from instanceof RelationList, 'A derived join input must be independent of any joined input.');
        $this->value = new DerivedSource((new SelectLowering())->lower($operation, $query));
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
    public function resolved(ResolvedColumn $resolution): ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference
    {
        Check::input(
            $resolution->depth === 0 && $resolution->relation === $this->input,
            'A derived reference must belong to its join input.'
        );
        $slots = $this->operation->facts->relation($this->input)->shape->slots;
        $position = false;
        for ($slot = $resolution->slot; $slot !== null; $slot = $slot->origin) {
            $position = array_search($slot, $slots, true);
            if ($position !== false) {
                break;
            }
        }
        Check::input($position !== false && isset($this->value->definition->columns[$position]), 'A derived reference must resolve to a defined field.');

        $kind = $this->value->columnKind($position);
        Check::input($kind !== null, 'A derived reference must resolve to a defined field.');

        return match ($kind) {
            ColumnKind::Text => new TextColumnReference($position),
            ColumnKind::Boolean => new BooleanColumnReference($position),
            ColumnKind::Int8 => new WideIntegerColumnReference($position),
            ColumnKind::Int4 => new ColumnReference($position),
        };
    }

    /**
     * Returns the independent model with no binding state.
     */
    public function source(): DerivedSource
    {
        return $this->value;
    }
}
