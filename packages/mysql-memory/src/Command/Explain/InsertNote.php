<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Scalar;

/**
 * Writes modern EXPLAIN's note for explicit inserted rows from semantic operands.
 * SET lists become column/value lists, and ROW spelling does not survive the note.
 * Verified through MySQL 8.4, including omitted values and duplicate-key assignments.
 *
 * @visibility MySqlMemory
 */
final class InsertNote
{
    /**
     * Returns the resolved write note for an explicit row source.
     */
    public function rows(MySqlStatement $operation, Session $session): ?string
    {
        $explain = $operation;
        $insert = $explain instanceof Explain ? $explain->statement : null;
        if (!$insert instanceof InsertRows && !$insert instanceof InsertSet && !$insert instanceof InsertQuery) {
            return null;
        }
        $values = $this->values($insert);
        if ($values === null || $insert->into->table->partitions !== []) {
            return null;
        }
        $database = $explain->database->value ?? $session->variables->database;
        $target = $insert->into->table->name;
        $table = Routine::quoted($target->schema->value ?? $database) . '.' . Routine::quoted($target->name->value);
        $writer = new ViewText($operation->statementInformation(), '', $database);
        $columns = $insert instanceof InsertSet ? array_column($insert->assignments, 'column') : $insert->into->columns?->columns;
        $names = array_map(static fn ($column): ?string => $column instanceof ColumnUse ? $table . '.' . Routine::quoted($column->name->value) : null, $columns ?? []);
        $rows = array_map(fn (array $row): ?string => $this->row($row, $writer), $values);
        $updates = $this->updates($insert->onDuplicate, $table, $writer);
        if (in_array(null, [...$names, ...$rows, ...$updates], true)) {
            return null;
        }
        $into = $insert->into;
        return ($into->replace ? 'replace' : 'insert') . ($into->priority === null ? '' : ' ' . strtolower($into->priority->value)) . ($into->ignore ? ' ignore' : '') . ' into ' . $table . ($columns === null ? '' : ' (' . implode(',', $names) . ')') . ' values ' . implode(',', $rows) . ($updates === [] ? '' : ' on duplicate key update ' . implode(',', $updates));
    }

    /**
     * Formats duplicate-key assignments after their operand conditions have prepared.
     *
     * @param list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $assignments The retained assignments
     * @return list<string|null>
     */
    public function updates(array $assignments, string $table, ViewText $writer): array
    {
        $updates = [];
        foreach ($assignments as $assignment) {
            \SqlSemantics\Diagnostic\Check::invariant($assignment instanceof Assignment, 'A write note follows successful duplicate-key preparation.');
            $value = $this->value($assignment->value, $writer);
            $updates[] = $value === null ? null : $table . '.' . Routine::quoted($assignment->column->name->value) . ' = ' . $value;
        }
        return $updates;
    }

    /**
     * Extracts explicit row operands without changing their semantic identities.
     *
     * @return list<list<Scalar|DefaultRequest>>|null
     */
    public function values(InsertRows|InsertSet|InsertQuery $insert): ?array
    {
        if ($insert instanceof InsertQuery) {
            $source = $insert->values();
            return $source === null ? null : array_column($source->rows, 'values');
        }
        if ($insert->alias !== null) {
            return null;
        }
        $rows = $insert instanceof InsertSet ? [array_column($insert->assignments, 'value')] : array_column($insert->rows, 'values');
        $prepared = [];
        foreach ($rows as $row) {
            $items = [];
            foreach ($row as $value) {
                \SqlSemantics\Diagnostic\Check::invariant($value instanceof Scalar || $value instanceof DefaultRequest, 'A write note follows successful scalar preparation.');
                $items[] = $value;
            }
            $prepared[] = $items;
        }
        return $prepared;
    }

    /**
     * @param list<Scalar|DefaultRequest> $values The original row values
     */
    public function row(array $values, ViewText $writer): ?string
    {
        $items = array_map(fn (Scalar|DefaultRequest $value): ?string => $this->value($value, $writer), $values);
        return in_array(null, $items, true) ? null : '(' . implode(',', $items) . ')';
    }

    /**
     * DEFAULT remains a request for the column's default in the diagnostic text.
     */
    public function value(Scalar|DefaultRequest $value, ViewText $writer): ?string
    {
        return $value instanceof DefaultRequest ? 'default' : $writer->expressions->scalar($value);
    }
}
