<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn;

/**
 * Reports an invoked generation declaration from its semantic operands.
 *
 * @visibility MySqlMemory
 */
final class GenerationCondition
{
    /**
     * Produces the retained condition after destination and column checks.
     */
    public function error(ColumnDefinition $column, string $database, string $table, bool $temporaryFile = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts = null, ?\MySqlMemory\Session\Session $session = null): SqlError
    {
        $specification = $column->specification;
        \SqlSemantics\Diagnostic\Check::invariant($specification instanceof DeferredGeneratedColumn, 'An invoked generation condition has a deferred declaration.');
        $expression = $specification->expression;
        while ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || $expression instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped) {
            $expression = $expression->operand;
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands())->deferred($expression)) {
            try {
                (new ExpressionRules())->grouped($expression);
            } catch (SqlError $error) {
                return $error;
            }
        }
        $path = $temporaryFile ? '' : './' . $database . '/' . $table . '.frm';
        $systemTail = (new GenerationText())->systemTail($expression);
        if ($systemTail !== null || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->contains($expression)) {
            $error = StatementError::ParseError;
            $text = $systemTail ?? (new GenerationText())->tail($expression);
            return new SqlError($error, $error->message($text, 1), following: [[1033, "Incorrect information in file: '" . $path . "'"]]);
        }
        if ($facts !== null && $session !== null && ($search = (new GenerationSearch())->error($expression, $facts, $session)) !== null) {
            return $search;
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
            return new SqlError(StatementError::UnknownError, "Incorrect information in file: '" . $path . "'", number: 1033);
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand && $expression->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns) {
            return \MySqlMemory\Error\Family\QueryError::OperandColumns->error(1);
        }
        return ConstraintError::GeneratedSubquery->error($column->name->column->value);
    }

    /**
     * Empty generation definitions fail before individual generation expressions.
     *
     * @param list<ColumnDefinition> $columns Complete declarations in written order
     */
    public function columns(array $columns, string $database, string $table, bool $temporaryFile = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts = null, ?\MySqlMemory\Session\Session $session = null): SqlError
    {
        $first = null;
        foreach ($columns as $column) {
            if (!$column->specification instanceof DeferredGeneratedColumn) {
                continue;
            }
            $error = $this->error($column, $database, $table, $temporaryFile, $facts, $session);
            if ($error->getCode() === 1033) {
                return $error;
            }
            $first ??= $error;
        }
        \SqlSemantics\Diagnostic\Check::invariant($first !== null, 'A retained generation declaration has a deferred column.');
        return $first;
    }
}
