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
     * @param bool $stored Whether a generated column is STORED rather than VIRTUAL
     * @param string $expression The expression of a generated column as SHOW CREATE TABLE writes it
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
        public readonly bool $stored = false,
        public readonly string $expression = '',
    ) {
    }

    /**
     * Answers the same column with another domain.
     */
    public function withDomain(Domain $domain): self
    {
        return new self($this->name, $domain, $this->default, $this->autoIncrement, $this->onUpdateNow, $this->generated, $this->invisible, $this->declaration, $this->comment, $this->stored, $this->expression);
    }

    /**
     * Answers the same column with another default.
     */
    public function withDefault(Fill $default): self
    {
        return new self($this->name, $this->domain, $default, $this->autoIncrement, $this->onUpdateNow, $this->generated, $this->invisible, $this->declaration, $this->comment, $this->stored, $this->expression);
    }

    /**
     * Answers the same column computed by an expression: a generated column, STORED or VIRTUAL, and the expression as SHOW CREATE TABLE writes it.
     */
    public function withGeneration(Evaluable $generated, bool $stored, string $expression): self
    {
        return new self($this->name, $this->domain, Fill::none(), false, false, $generated, $this->invisible, $this->declaration, $this->comment, $stored, $expression);
    }

    /**
     * Tells whether the column admits NULL.
     */
    public function nullable(): bool
    {
        return $this->domain->nullable;
    }
}
