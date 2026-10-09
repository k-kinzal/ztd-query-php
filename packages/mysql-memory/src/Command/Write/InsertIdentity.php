<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Session\Variables;

/**
 * Tracks the insert id reported by an INSERT or REPLACE separately from the session's LAST_INSERT_ID().
 *
 * A successfully inserted generated value takes precedence over LAST_INSERT_ID(expr), then the
 * last explicit or updated AUTO_INCREMENT value is reported if any row was written. An unchanged
 * duplicate reports zero even with CLIENT_FOUND_ROWS. Explicit values do not change the session's
 * LAST_INSERT_ID(). Verified against MySQL 8.4.7 through PDO.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_last-insert-id.
 *
 * @visibility MySqlMemory
 */
final class InsertIdentity
{
    /**
     * The first generated value actually inserted.
     */
    public ?int $generated = null;

    private int $last = 0;

    private bool $written = false;

    /**
     * @param TableDefinition $table The definition identifying the AUTO_INCREMENT column
     */
    public function __construct(public readonly TableDefinition $table)
    {
    }

    /**
     * Records a successfully inserted row, including a replacement.
     *
     * @param list<int|float|string|null> $row
     */
    public function inserted(array $row, ?int $generated): void
    {
        $this->generated ??= $generated;
        $this->updated($row, true);
    }

    /**
     * Records the last duplicate row, even when it was unchanged; only changed rows make an id reportable.
     *
     * @param list<int|float|string|null> $row
     */
    public function updated(array $row, bool $changed): void
    {
        $position = $this->table->autoIncrementColumn();
        $this->last = $position === null ? 0 : (int) $row[$position];
        $this->written = $this->written || $changed;
    }

    /**
     * Updates LAST_INSERT_ID() for a generated value and answers the id for the OK packet, clearing the statement's function flag.
     */
    public function finish(Variables $variables): int
    {
        $id = $this->generated ?? ($variables->setByFunction ? $variables->lastInsertId : ($this->written ? $this->last : 0));
        if ($this->generated !== null) {
            $variables->lastInsertId = $this->generated;
        }
        $variables->setByFunction = false;

        return $id;
    }
}
