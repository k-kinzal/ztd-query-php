<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\SelectLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound as ParsedCompound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator as ParsedOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;

/**
 * Resolves each SQLite compound arm in its independently analyzed scope.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CompoundLowering
{
    /**
     * Lowers the arms with their own output names and scoped column slots.
     */
    public function lower(BoundStatement $operation, ParsedCompound $query): Compound
    {
        $selects = new SelectLowering();
        $values = new ValuesLowering();
        $first = $query->first instanceof ParsedSelect
            ? $selects->lower($operation, $query->first)
            : $values->lower($operation, $query->first);
        $steps = [];
        foreach ($query->steps as $part) {
            $operator = match ($part->operator) {
                ParsedOperator::UnionAll => CompoundOperator::UnionAll,
                ParsedOperator::Union => CompoundOperator::Union,
                ParsedOperator::Intersect => CompoundOperator::Intersect,
                ParsedOperator::Except => CompoundOperator::Except,
            };
            $arm = $part->query instanceof ParsedSelect
                ? $selects->lower($operation, $part->query)
                : $values->lower($operation, $part->query);
            $steps[] = new CompoundStep($operator, $arm);
        }

        $clauses = new QueryClauses();

        return new Compound(
            $first,
            $steps,
            $clauses->ordering($query->orderBy),
            $clauses->limit($query->limit, new SourceBinding($operation, null)),
        );
    }
}
