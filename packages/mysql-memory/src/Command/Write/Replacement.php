<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Program\Triggers;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\References;

/**
 * Resolves the conflict of a row REPLACE writes with a row of the table.
 *
 * The existing row is deleted, after its BEFORE DELETE triggers and the foreign keys that
 * reference it, and before its AFTER DELETE triggers, which counts one affected row. A conflict
 * on the last unique key of a table without DELETE triggers updates the existing row in place
 * instead, which counts one affected row when the row is unchanged and two otherwise.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/replace.html.
 *
 * @visibility MySqlMemory
 */
final class Replacement
{
    /**
     * @param StoredTable $table The table written
     * @param Context $context The statement
     * @param Session $session The session
     */
    public function __construct(
        public readonly StoredTable $table,
        public readonly Context $context,
        public readonly Session $session,
    ) {
    }

    /**
     * Resolves a conflict of a new row with an existing row: answers the rows affected, and whether the new row took the place of the existing one.
     *
     * @param list<int|float|string|null> $row The new row
     * @param int $existing The number of the existing row
     * @param Key $key The unique key the rows conflict on
     * @return array{int, bool}
     *
     * @throws SqlError When a trigger fails, a foreign key refuses the deletion, or the row cannot be locked
     */
    public function replace(array $row, int $existing, Key $key): array
    {
        $data = $this->table->data;
        $replaced = $data->rows[$existing];
        $triggers = new Triggers($this->session, $this->table);
        $triggers->before('DELETE', null, $replaced, $this->context);
        (new References($this->session, $this->context))->deleting($this->table, $replaced);
        if ($key === $this->lastUnique() && !$triggers->has('DELETE')) {
            $same = $data->rows[$existing] === $row;
            $this->session->transaction->write($this->table, $existing);
            $data->update($existing, $row);

            return [$same ? 1 : 2, true];
        }
        $this->session->transaction->write($this->table, $existing);
        $data->delete($existing);
        $triggers->after('DELETE', null, $replaced, $this->context);

        return [1, false];
    }

    /**
     * Answers the last unique key of the table, which REPLACE resolves by updating the row in place.
     */
    public function lastUnique(): ?Key
    {
        $last = null;
        foreach ($this->table->definition->keys as $key) {
            if ($key->unique()) {
                $last = $key;
            }
        }

        return $last;
    }
}
