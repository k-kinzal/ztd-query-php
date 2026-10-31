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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
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
    public function projection(MySqlStatement $operation, Session $session, Connection $connection, bool $impossible): ?string
    {
        $explain = $operation;
        $query = $explain instanceof Explain && $explain->statement instanceof QueryRoot ? $explain->statement->query : null;
        if (!$query instanceof Select || !$this->simple($query, true)) {
            return null;
        }
        $writer = new ViewText($operation->statementInformation(), '', $explain->database->value ?? $session->variables->database);
        $compiler = (new Planner($query, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session)))->compiler;
        if ($query->where !== null && $compiler->constancy($query->where) !== \MySqlMemory\Evaluation\Compile\Constancy::Resolved) {
            return null;
        }
        $items = [];
        foreach ($query->queryInformation()->projection as $field) {
            if (!$field instanceof Field) {
                return null;
            }
            $text = $this->item($field, $writer, $compiler);
            if ($text === null) {
                return null;
            }
            $items[] = $text . ' AS ' . Routine::quoted($field->name->value ?? '');
        }
        $from = $query->from === null ? '' : $writer->relation($query->from);
        return $from === null ? null : '/* select#1 */ select ' . implode(',', $items) . ($from === '' ? '' : ' from ' . $from) . ($impossible ? ' where ' . ($session->settings()->legacy() ? '0' : 'false') : '');
    }
    /**
     * Writes one resolved output expression, including a bound parameter or expanded star.
     */
    public function item(Field $field, ViewText $writer, \MySqlMemory\Evaluation\Compile\Compiler $compiler): ?string
    {
        $expression = $field->expression;
        $text = $field->resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn ? $this->column($field->resolution, $writer) : ($expression === null ? null : $writer->expressions->scalar($expression));
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall && $expression->function === \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Database) {
            $text = 'database()';
        }
        if ($expression instanceof Parameter) {
            $compiled = $compiler->compile($expression, new Scope());
            $value = $compiled->evaluate(new Frame($compiler->connection->context));
            $text = $value === null ? 'NULL' : ($compiled->domain()->kind->numeric() ? (string) $value : "'" . strtr((string) $value, ['\\' => '\\\\', "'" => "\\'"]) . "'");
        }
        return $text;
    }

    /**
     * Identifies projections whose table and expression spellings need no filter or ordering rewrite.
     */
    public function simple(Select $query, bool $filter = false): bool
    {
        return ($query->from === null || $query->from instanceof TableReference) && ($filter || $query->where === null) && $query->groupBy === null && $query->having === null && $query->orderBy === [] && $query->limit === null && $query->options === [] && $query->windows === [];
    }
    /**
     * Qualifies expanded stars and explicit columns with their resolved database and alias.
     */
    public function column(\SqlSemantics\Statement\Reference\Column\ResolvedColumn $resolution, ViewText $writer): ?string
    {
        $relation = $resolution->relation;
        if ($relation instanceof TableReference && $relation->alias !== null && $resolution->slot->name !== null) {
            return Routine::quoted($relation->name->schema->value ?? $writer->database) . '.' . Routine::quoted($relation->alias->value) . '.' . Routine::quoted($resolution->slot->name->value);
        }
        return $writer->column($resolution);
    }
}
