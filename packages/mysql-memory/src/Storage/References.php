<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;

/**
 * Keeps the foreign keys of the tables a statement writes, as InnoDB keeps them row by row.
 *
 * A child row whose referencing columns are all not NULL must match a row of the referenced
 * table (ER_NO_REFERENCED_ROW_2). Deleting a parent row, or changing its referenced columns, that
 * child rows match is refused (ER_ROW_IS_REFERENCED_2) unless the key cascades: CASCADE deletes
 * the child rows or carries the new values to them, SET NULL sets their referencing columns to
 * NULL; SET DEFAULT is refused as RESTRICT is. A cascade goes on through the keys of the child
 * rows, at most 15 levels deep. With foreign_key_checks off nothing is checked or cascaded. Every
 * rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility MySqlMemory
 */
final class References
{
    /**
     * The deepest a cascade goes.
     */
    public const DEPTH = 15;

    /**
     * @param Session $session The session writing, whose tables the keys reference
     * @param Context $context The statement writing
     */
    public function __construct(public readonly Session $session, public readonly Context $context)
    {
    }

    /**
     * Tells whether the session checks foreign keys.
     */
    public function enabled(): bool
    {
        return $this->session->variables->read('foreign_key_checks') !== 'OFF';
    }

    /**
     * Answers the error a foreign key a child row violates raises: ER_NO_REFERENCED_ROW_2, or ER_ROW_IS_REFERENCED_2 for a parent row.
     */
    public function violation(StoredTable $child, ForeignKey $key, bool $parent): SqlError
    {
        $text = '`' . $child->definition->schema . '`.`' . $child->definition->name . '`, ' . $key->text($child->definition, $this->session->settings()->legacy());

        return ($parent ? ConstraintError::RowIsReferenced : ConstraintError::NoReferencedRow)->error($text);
    }

    /**
     * Answers the first foreign key of a table a row violates: one whose referencing columns, all not NULL and changed from the old row, no row of the referenced table matches.
     *
     * @param list<int|float|string|null> $row
     * @param list<int|float|string|null>|null $old The row before an update, or null for a new row
     *
     * @throws SqlError When a referenced row cannot be locked
     */
    public function orphan(StoredTable $table, array $row, ?array $old = null): ?ForeignKey
    {
        if (!$this->enabled()) {
            return null;
        }
        foreach ($table->definition->foreignKeys as $key) {
            $values = array_map(static fn (int $position) => $row[$position], $key->columns);
            if (in_array(null, $values, true) || ($old !== null && $values === array_map(static fn (int $position) => $old[$position], $key->columns))) {
                continue;
            }
            $self = $key->references($table->definition->schema, $table->definition->name);
            $parent = $self ? $table : $this->session->instance->dictionary->schema($key->parentSchema)?->table($key->parentTable);
            if ($parent === null || $this->matching($parent, $this->positions($parent, $key), $values, $self ? $row : null) === []) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Answers the positions of the referenced columns of a key in the referenced table.
     *
     * @return list<int>
     */
    public function positions(StoredTable $parent, ForeignKey $key): array
    {
        return array_map(static fn (string $name): int => $parent->definition->position($name) ?? 0, $key->parentColumns);
    }

    /**
     * Answers the numbers of the rows of a table whose columns at some positions equal values, compared as the columns compare, and locks them shared.
     *
     * The check reads the latest rows, and the committed versions of the rows other open
     * transactions changed or deleted: a matching row another transaction holds is waited for,
     * and the check starts again once it is locked, as InnoDB locks the records a foreign key
     * check reads.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html.
     *
     * @param list<int> $positions
     * @param list<int|float|string|null> $values
     * @param list<int|float|string|null>|null $pending A row of the table not stored yet, which a self-referencing row may match
     * @return list<int>
     *
     * @throws SqlError When a matching row cannot be locked
     */
    public function matching(StoredTable $table, array $positions, array $values, ?array $pending = null): array
    {
        $wanted = $this->key($table, $positions, $values);
        $transaction = $this->session->transaction;
        $numbers = [];
        $contended = null;
        foreach ([$transaction->access->rows($table, LockMode::Shared), $transaction->system->earlier($table, $transaction)] as $candidates) {
            foreach ($candidates as $number => $row) {
                if ($row === null || isset($numbers[$number]) || $this->key($table, $positions, array_map(static fn (int $position) => $row[$position], $positions)) !== $wanted) {
                    continue;
                }
                if ($transaction->access->contended($table, $number, LockMode::Shared)) {
                    $contended ??= $number;
                }
                $numbers[$number] = true;
            }
        }
        if ($contended !== null) {
            $transaction->access->lock($table, $contended, LockMode::Shared);

            return $this->matching($table, $positions, $values, $pending);
        }
        foreach (array_keys($numbers) as $number) {
            $transaction->access->lock($table, $number, LockMode::Shared);
        }
        $numbers = array_keys($numbers);
        if ($pending !== null && $numbers === [] && $this->key($table, $positions, array_map(static fn (int $position) => $pending[$position], $positions)) === $wanted) {
            $numbers[] = 0;
        }

        return $numbers;
    }

    /**
     * Answers the comparison key of values of columns of a table.
     *
     * @param list<int> $positions
     * @param list<int|float|string|null> $values
     */
    public function key(StoredTable $table, array $positions, array $values): string
    {
        $text = '';
        foreach ($positions as $index => $position) {
            $text .= Order::key($values[$index], $table->definition->columns[$position]->domain) . "\0";
        }

        return $text;
    }

    /**
     * Answers each table of the server with a foreign key that references a table, with the key.
     *
     * @return list<array{StoredTable, ForeignKey}>
     */
    public function children(StoredTable $parent): array
    {
        $children = [];
        foreach ($this->session->instance->dictionary->schemas as $schema) {
            foreach ($schema->tables as $table) {
                foreach ($table->definition->foreignKeys as $key) {
                    if ($key->references($parent->definition->schema, $parent->definition->name)) {
                        $children[] = [$table, $key];
                    }
                }
            }
        }

        return $children;
    }

    /**
     * Applies the ON DELETE actions of the keys that reference a table to the child rows of a row about to be deleted, or refuses the delete.
     *
     * @param list<int|float|string|null> $row
     *
     * @throws SqlError When a child row refuses it, or a cascade goes too deep
     */
    public function deleting(StoredTable $parent, array $row, int $depth = 0): void
    {
        $this->changing($parent, $row, null, $depth);
    }

    /**
     * Applies the ON UPDATE actions of the keys that reference a table to the child rows of a row whose referenced columns change, or refuses the change.
     *
     * @param list<int|float|string|null> $old
     * @param list<int|float|string|null> $new
     *
     * @throws SqlError When a child row refuses it, or a cascade goes too deep
     */
    public function updating(StoredTable $parent, array $old, array $new, int $depth = 0): void
    {
        $this->changing($parent, $old, $new, $depth);
    }

    /**
     * Applies the actions of the keys that reference a table to the child rows of a parent row deleted, or changed into a new row.
     *
     * @param list<int|float|string|null> $old
     * @param list<int|float|string|null>|null $new The row after an update, or null for a delete
     *
     * @throws SqlError When a child row refuses it, or a cascade goes too deep
     */
    public function changing(StoredTable $parent, array $old, ?array $new, int $depth): void
    {
        if (!$this->enabled()) {
            return;
        }
        foreach ($this->children($parent) as [$child, $key]) {
            $positions = $this->positions($parent, $key);
            $values = array_map(static fn (int $position) => $old[$position], $positions);
            if (in_array(null, $values, true) || ($new !== null && $this->key($parent, $positions, $values) === $this->key($parent, $positions, array_map(static fn (int $position) => $new[$position], $positions)))) {
                continue;
            }
            $numbers = $this->matching($child, $key->columns, $values);
            if ($numbers === []) {
                continue;
            }
            $action = $new === null ? $key->onDelete : $key->onUpdate;
            if ($action !== ReferenceOption::Cascade && $action !== ReferenceOption::SetNull) {
                throw $this->violation($child, $key, true);
            }
            if ($depth >= self::DEPTH) {
                throw ConstraintError::ForeignCascadeDepth->error(self::DEPTH);
            }
            $this->session->transaction->touch($child);
            foreach ($numbers as $number) {
                $this->cascade($child, $key, $number, $action === ReferenceOption::SetNull ? null : array_map(static fn (int $position) => $new[$position] ?? null, $positions), $new === null && $action === ReferenceOption::Cascade, $depth + 1);
            }
        }
    }

    /**
     * Carries an action to one child row: deletes it, or sets its referencing columns to new values or NULL, going on through the keys that reference the child table.
     *
     * @param list<int|float|string|null>|null $values The new values of the referencing columns, or null to set them to NULL
     *
     * @throws SqlError When a row further down refuses it
     */
    public function cascade(StoredTable $child, ForeignKey $key, int $number, ?array $values, bool $delete, int $depth): void
    {
        $row = $child->data->rows[$number] ?? null;
        if ($row === null) {
            return;
        }
        if ($delete) {
            $this->changing($child, $row, null, $depth);
            $this->session->transaction->write($child, $number);
            $child->data->delete($number);

            return;
        }
        $changed = $row;
        foreach ($key->columns as $index => $position) {
            array_splice($changed, $position, 1, [$values === null ? null : $values[$index]]);
        }
        $changed = (new Writer($child, $this->context))->generate($changed, new Store($this->context, 1, $child->definition->name));
        $this->changing($child, $row, $changed, $depth);
        $this->session->transaction->write($child, $number);
        $child->data->update($number, $changed);
    }
}
