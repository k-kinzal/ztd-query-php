<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Shape\Field;

/**
 * Writes the rewritten-query note for an unrestricted projection or table scan.
 *
 * Resolved output fields provide aliases; bound parameter values replace marker
 * operands. Other plan rewrites require their own structural representation.
 * No original request text is used to manufacture the note.
 *
 * @visibility MySqlMemory
 */
final class QueryNote
{
    /**
     * Returns the resolved projection note when no filter, grouping or ordering rewrite is required.
     */
    public function projection(Operation $operation, Session $session, Connection $connection): ?string
    {
        $explain = $operation->statement;
        $query = $explain instanceof Explain && $explain->statement instanceof QueryRoot ? $explain->statement->query : null;
        if (!$query instanceof Select || !$this->simple($query)) {
            return null;
        }
        $writer = new ViewText($operation->facts, '', $explain->database->value ?? $session->variables->database);
        $compiler = (new Planner($query, $operation->facts, $session->settings(), $connection, $session->instance->dictionary))->compiler;
        $items = [];
        foreach ($operation->facts->query($query)->projection as $field) {
            if (!$field instanceof Field || $field->expression === null) {
                return null;
            }
            $expression = $field->expression;
            $text = $writer->expressions->scalar($expression);
            if ($expression instanceof Parameter) {
                $compiled = $compiler->compile($expression, new Scope());
                $value = $compiled->evaluate(new Frame($connection->context));
                $text = $value === null ? 'NULL' : ($compiled->domain()->kind->numeric() ? (string) $value : "'" . strtr((string) $value, ['\\' => '\\\\', "'" => "\\'"]) . "'");
            }
            if ($text === null) {
                return null;
            }
            $items[] = $text . ' AS ' . Routine::quoted($field->name->value ?? '');
        }
        $from = $query->from === null ? '' : $writer->relation($query->from);
        return $from === null ? null : '/* select#1 */ select ' . implode(',', $items) . ($from === '' ? '' : ' from ' . $from);
    }
    /**
     * Identifies projections whose table and expression spellings need no filter or ordering rewrite.
     */
    public function simple(Select $query): bool
    {
        return ($query->from === null || $query->from instanceof TableReference) && $query->where === null && $query->groupBy === null && $query->having === null && $query->orderBy === [] && $query->limit === null && $query->options === [] && $query->windows === [];
    }
}
