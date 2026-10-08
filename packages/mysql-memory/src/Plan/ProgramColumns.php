<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use SqlSemantics\Statement\Shape\Field;

/**
 * The metadata of the result columns that read a variable of a stored program or call a stored function.
 *
 * A column that reads a variable carries the flags of a column of its type, without BINARY
 * unless it is a string, and BLOB for a BLOB or TEXT type; a column a stored function returns
 * is flagged BLOB for a BLOB or TEXT type (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query_response_text_resultset_column_definition.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramColumns
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Tells whether an output column is a call of a stored function.
     */
    public function called(Field $field): bool
    {
        $call = $field->expression;

        return $call instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall && ($call->schema !== null || \MySqlMemory\Evaluation\Function\Library::instance()->find($call->name->value) === null);
    }

    /**
     * Answers the flags of an output column that reads a variable of a stored program, those of a column of its type without BINARY unless it is a string, or that a stored function returns: BLOB for a BLOB or TEXT type.
     */
    public function flagged(Field $field, bool $variable): ?ColumnOrigin
    {
        $domain = $field->expression === null ? null : $this->planner->compiler->resolved($field->expression);
        if ($domain === null) {
            return null;
        }
        $blob = $domain->field->blob() ? \MySqlMemory\Result\ColumnFlag::Blob->value : 0;
        if (!$variable) {
            return $blob === 0 ? null : new ColumnOrigin('', '', '', '', $blob);
        }
        $flags = $domain->flags() | $blob;

        return new ColumnOrigin('', '', '', '', $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String ? $flags : $flags & ~\MySqlMemory\Result\ColumnFlag::Binary->value, true);
    }
}
