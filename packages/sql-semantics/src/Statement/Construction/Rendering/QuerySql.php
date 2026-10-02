<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Rendering;

use SqlSemantics\Statement\Construction\Query as Q;
use SqlSemantics\Statement\Query\Quantifier;

/**
 * Spells only the explicit inputs of a new query, without borrowing another root.
 * @visibility SqlSemantics
 */
final class QuerySql
{
    /**
     * Each query form has a constructive spelling over its actual inputs.
     */
    public function write(Q\SelectDefinition|Q\RowsDefinition $input): string
    {
        $expression = new ExpressionSql();
        if ($input instanceof Q\RowsDefinition) {
            return 'VALUES ' . implode(', ', array_map(static fn (Q\RowDefinition $row): string => '(' . implode(', ', array_map($expression->write(...), $row->expressions)) . ')', $input->rows));
        }
        $sql = 'SELECT' . ($input->quantifier === Quantifier::Default ? '' : ' ' . $input->quantifier->value) . ' ' . implode(', ', array_map(static fn (Q\FieldDefinition $field): string => $expression->write($field->expression) . ($field->alias === null ? '' : ($field->explicitAlias ? ' AS ' : ' ') . $field->alias->toString()), $input->projection->fields));
        $sql .= $input->from->items === [] ? '' : ' FROM ' . implode(', ', array_map(static fn (Q\NamedInput $item): string => $item->name->toString() . ($item->alias === null ? '' : ($item->explicitAlias ? ' AS ' : ' ') . $item->alias->toString()), $input->from->items));
        $sql .= $input->where === null ? '' : ' WHERE ' . $expression->write($input->where);
        return $sql . ($input->limit === null ? '' : ' ' . $this->limit($input->limit));
    }

    /**
     * Count and offset retain their roles in either spelling.
     */
    public function limit(Q\LimitDefinition $input): string
    {
        $expression = new ExpressionSql();
        \SqlSemantics\Statement\Validation\Check::input(!$input->commaSyntax || $input->offset !== null, 'Comma LIMIT requires both count and offset inputs.');
        return $input->commaSyntax && $input->offset !== null
            ? 'LIMIT ' . $expression->write($input->offset) . ', ' . $expression->write($input->count)
            : 'LIMIT ' . $expression->write($input->count) . ($input->offset === null ? '' : ' OFFSET ' . $expression->write($input->offset));
    }
}
