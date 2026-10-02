<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Query as Q;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Validation\Check;

/**
 * Connects explicit new query requests to actual snapshot clauses and operand positions.
 * @visibility SqlSemantics
 */
final class QueryMatch
{
    /**
     * Independently checks every currently implemented SELECT slot before publication.
     */
    public function select(Catalog|Scope|SqliteAliasScope $context, C\Query\SelectDefinition $input, C\SelectSnapshot|Q\Select|Q\ScopedSelect $actual): void
    {
        (new EnvironmentMatch())->check($context, $input->from, $actual->scope);
        $fields = $actual instanceof C\SelectSnapshot ? $actual->projection : $actual->fields();
        Check::invariant($fields->scope === $actual->scope && count($input->projection->fields) === count($fields->items), 'Every projection request must have its actual ordered output slot.');
        $scalars = new ScalarMatch();
        foreach ($input->projection->fields as $index => $field) {
            $output = $fields->items[$index];
            Check::invariant(NamesMatch::same($field->alias, $output->alias) && $field->explicitAlias === $output->explicitAlias, 'A field must retain exactly its requested alias declaration.');
            $scalars->check($field->expression, $output->expression, $actual->scope);
        }
        Check::invariant($input->quantifier === $actual->quantifier, 'SELECT must retain its requested duplicate handling.');
        Check::invariant(($input->where === null) === ($actual->where === null), 'SELECT must retain whether a predicate was supplied.');
        if ($input->where !== null && $actual->where !== null) {
            $aliases = array_values(array_filter($fields->items, static fn (\SqlSemantics\Statement\Projection\Field $field): bool => $field->alias !== null));
            $scalars->check($input->where, $actual->where, new SqliteAliasScope($fields, ...$aliases));
        }
        $this->limit($actual->scope->catalog, $input->limit, $actual->limit);
    }

    /**
     * Tuple count, each width, order, and expression ownership must all correspond.
     */
    public function rows(Catalog|Scope|SqliteAliasScope $context, C\Query\RowsDefinition $input, C\RowsSnapshot|Q\Rows|Q\ScopedRows $actual): void
    {
        (new EnvironmentMatch())->check($context, new C\Query\Inputs(), $actual->scope);
        Check::invariant(count($input->rows) === count($actual->rows), 'VALUES must retain every requested tuple.');
        $scalars = new ScalarMatch();
        foreach ($input->rows as $position => $row) {
            $output = $actual->rows[$position];
            Check::invariant($output->scope === $actual->scope && count($row->expressions) === count($output->expressions), 'A VALUES tuple must retain its exact width and evaluation scope.');
            foreach ($row->expressions as $index => $expression) {
                $scalars->check($expression, $output->expressions[$index], $actual->scope);
            }
        }
    }

    /**
     * LIMIT uses an independent scope and preserves count versus offset even in comma notation.
     */
    public function limit(Catalog $catalog, ?C\Query\LimitDefinition $input, ?Q\SqliteLimit $actual): void
    {
        Check::invariant(($input === null) === ($actual === null), 'The row restriction must retain its presence.');
        if ($input === null || $actual === null) {
            return;
        }
        (new EnvironmentMatch())->check($catalog, new C\Query\Inputs(), $actual->scope);
        Check::invariant($input->commaSyntax === $actual->commaSyntax && ($input->offset === null) === ($actual->offset === null), 'Count and offset must retain their requested notation and presence.');
        (new ScalarMatch())->check($input->count, $actual->count, $actual->scope);
        if ($input->offset !== null && $actual->offset !== null) {
            (new ScalarMatch())->check($input->offset, $actual->offset, $actual->scope);
        }
    }
}
