<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Checks a changed row against the constraints of its table, in the order the server checks them: the CHECK constraints, the unique keys, then the foreign keys of the row and those that reference it.
 *
 * A row a constraint refuses fails the statement, or with IGNORE is left unchanged with the
 * error as a warning (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility MySqlMemory
 */
final class Constrained
{
    /**
     * @param Writer $writer The writer of the table
     * @param References $references The keeper of the foreign keys
     * @param list<int>|null $selected The partitions the statement names, which a changed row must stay in; null when it names none
     */
    public function __construct(public readonly Writer $writer, public readonly References $references, public readonly ?array $selected = null)
    {
    }

    /**
     * Tells whether an updated row may replace the row it was, applying the actions of the foreign keys that reference it.
     *
     * @param list<int|float|string|null> $row
     * @param list<int|float|string|null> $old
     *
     * @throws SqlError When a constraint refuses the row and the statement has no IGNORE
     */
    public function updated(array $row, array $old, int $number, bool $ignore): bool
    {
        $writer = $this->writer;
        $check = $writer->violated($row);
        if ($check !== null) {
            return $this->refused(DataError::CheckConstraintViolated->error($check->name), $ignore);
        }
        $partitioning = $writer->table->definition->partitioning;
        if ($partitioning !== null) {
            try {
                (new Partitions($writer->table->definition, $partitioning, $writer->context))->place($row, $this->selected);
            } catch (SqlError $error) {
                if (!$error->error instanceof \MySqlMemory\Error\Family\PartitionError) {
                    throw $error;
                }

                return $this->refused($error, $ignore);
            }
        }
        $conflict = $writer->conflict($row, $number);
        if ($conflict !== null) {
            if ($ignore) {
                $writer->ignored($row, $conflict[1]);

                return false;
            }
            throw $writer->duplicate($row, $conflict[1]);
        }
        $orphan = $this->references->orphan($writer->table, $row, $old);
        if ($orphan !== null) {
            return $this->refused($this->references->violation($writer->table, $orphan, false), $ignore);
        }
        try {
            $this->references->updating($writer->table, $old, $row);
        } catch (SqlError $error) {
            if ($error->error !== ConstraintError::RowIsReferenced) {
                throw $error;
            }

            return $this->refused($error, $ignore);
        }

        return true;
    }

    /**
     * Refuses a row: raises the error, or with IGNORE records it as a warning and answers false.
     *
     * @throws SqlError Without IGNORE
     */
    public function refused(SqlError $error, bool $ignore): bool
    {
        if (!$ignore) {
            throw $error;
        }
        $this->writer->context->diagnostics->warning($error->error, $error->getMessage());

        return false;
    }
}
