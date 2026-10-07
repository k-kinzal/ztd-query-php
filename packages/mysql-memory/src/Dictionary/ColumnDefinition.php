<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Statement\Declaration\Column;

/**
 * One column of a stored table: its name, domain, default, and how the server fills it.
 *
 * @visibility MySqlMemory
 */
final class ColumnDefinition
{
    /**
     * @param string $name The column name as declared
     * @param Domain $domain The domain of the stored values; its nullability is the column's
     * @param Fill $default What an insert that names no value stores
     * @param bool $autoIncrement Whether the column takes the next AUTO_INCREMENT value
     * @param bool $onUpdateNow Whether an update that changes the row stores the current time
     * @param Evaluable|null $generated The expression a generated column is computed by
     * @param bool $invisible Whether `SELECT *` leaves the column out
     * @param Column|null $declaration The column declaration SQL Semantics resolves names against
     * @param string $comment The comment of the column
     */
    public function __construct(
        public readonly string $name,
        public readonly Domain $domain,
        public readonly Fill $default,
        public readonly bool $autoIncrement = false,
        public readonly bool $onUpdateNow = false,
        public readonly ?Evaluable $generated = null,
        public readonly bool $invisible = false,
        public readonly ?Column $declaration = null,
        public readonly string $comment = '',
    ) {
    }

    /**
     * Tells whether the column admits NULL.
     */
    public function nullable(): bool
    {
        return $this->domain->nullable;
    }
}
