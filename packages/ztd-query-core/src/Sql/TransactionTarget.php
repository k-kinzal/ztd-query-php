<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * Whatever a transaction statement is carried out against.
 *
 * A statement read out of SQL says which of the six things it is, and carrying
 * that out is someone else's work. Saying only that much here lets the
 * statement stay something read out of text: it hands itself to a target
 * without naming what keeps the transaction state, and without giving up the
 * operation and savepoint name it holds.
 */
interface TransactionTarget
{
    /**
     * Opens a transaction.
     */
    public function begin(): void;

    /**
     * Keeps everything the transaction wrote.
     */
    public function commit(): void;

    /**
     * Undoes everything the transaction wrote.
     */
    public function rollBack(): void;

    /**
     * Marks a point the transaction can be brought back to.
     *
     * @param string $name Name the point is marked with
     *
     * @throws InvalidDefinitionException When the statement named no savepoint
     */
    public function savepoint(string $name): void;

    /**
     * Undoes everything written since a point was marked.
     *
     * @param string $name Name the point was marked with
     *
     * @throws InvalidDefinitionException When the statement named no savepoint, or nothing marked that point
     */
    public function rollBackTo(string $name): void;

    /**
     * Forgets a point, keeping everything written since it was marked.
     *
     * @param string $name Name the point was marked with
     *
     * @throws InvalidDefinitionException When the statement named no savepoint, or nothing marked that point
     */
    public function release(string $name): void;
}
