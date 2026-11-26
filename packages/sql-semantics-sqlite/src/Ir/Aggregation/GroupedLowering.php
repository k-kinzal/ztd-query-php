<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Resolves an ordinary SQLite grouped projection through its bound declarations.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GroupedLowering
{
    /**
     * Recognizes the ordinary grouped query or the builtin COUNT result.
     */
    public function applies(Select $statement): bool
    {
        if ($statement->groupBy !== [] && $statement->from instanceof TableInput) {
            return true;
        }
        foreach ($statement->columns as $column) {
            if ($column instanceof ResultColumn && $column->expression instanceof FunctionCall
                && strtolower($column->expression->name->value) === 'count') {
                return true;
            }
        }

        return false;
    }

    /**
     * Binds the selected fields, group keys and filters.
     */
    public function lower(BoundStatement $operation, Select $statement): GroupedSelect
    {
        Check::input(
            ($statement->from === null || $statement->from instanceof TableInput)
                && $statement->windows === [] && $statement->orderBy === []
                && $statement->limit === null && $statement->quantifier !== SetQuantifier::Distinct,
            'A grouped projection requires an ordinary unbounded input without result modifiers.',
        );
        $source = new SourceBinding($operation, $statement->from);
        $binding = new GroupBinding($operation, $source);
        $keys = [];
        foreach ($statement->groupBy as $expression) {
            $keys[] = $binding->key($expression);
        }
        $where = (new QueryClauses())->predicate($statement->where, $source);
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A grouped projection requires a resolved output shape.');
        $columns = [];
        foreach ($statement->columns as $index => $target) {
            Check::input($target instanceof ResultColumn, 'A grouped projection requires explicit result fields.');
            $name = $fields->at($index)->name;
            Check::input($name !== null, 'A grouped result must have a resolved name.');
            $value = $target->expression instanceof FunctionCall
                ? (new CountBinding())->lower($target->expression, $binding)
                : $source->reference($target->expression);
            $columns[] = new GroupColumn($value, new Identifier($name->value));
        }
        $having = $statement->having === null ? null : (new GroupFilterLowering())->lower($statement->having, $binding, $source);

        return new GroupedSelect($source->source(), $keys, $columns, $where, $having);
    }
}
