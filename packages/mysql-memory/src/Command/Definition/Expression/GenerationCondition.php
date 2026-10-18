<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn;

/**
 * Reports an invoked MySQL 5.7 generation declaration from its semantic operands.
 *
 * @visibility MySqlMemory
 */
final class GenerationCondition
{
    /**
     * Produces the retained condition after destination and column checks.
     */
    public function error(ColumnDefinition $column, string $database, string $table): SqlError
    {
        $specification = $column->specification;
        \SqlSemantics\Diagnostic\Check::invariant($specification instanceof DeferredGeneratedColumn, 'An invoked generation condition has a deferred declaration.');
        $expression = $specification->expression;
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->contains($expression)) {
            $error = StatementError::ParseError;
            $text = (new GenerationText())->tail($expression);
            return new SqlError($error, $error->message($text, 1), following: [[1033, "Incorrect information in file: './" . $database . '/' . $table . ".frm'"]]);
        }
        if ((new \MySqlMemory\Evaluation\Compile\Walker())->find($expression, \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable::class) !== []) {
            return new SqlError(StatementError::UnknownError, "Incorrect information in file: './" . $database . '/' . $table . ".frm'", number: 1033);
        }
        return ConstraintError::GeneratedSubquery->error($column->name->column->value);
    }
}
