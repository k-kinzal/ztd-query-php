<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * Separates SQL values without changing quoted content or lexical adjacency.
 *
 * @visibility SqlSemantics
 */
final class Writer
{
    private string $sql = '';
    private string $previous = '';
    private bool $previousIdentifier = false;

    /**
     * Emits one fixed SQL word or an indivisible argument value.
     */
    public function append(string $value, bool $identifier = false): void
    {
        if ($value === '') {
            return;
        }
        $separator = ' ';
        if ($this->sql === '' || ($value === '(' && !$this->previousIdentifier) || ($value === '.' && $this->previousIdentifier) || $this->previous === '.' || $this->previous === '@') {
            $separator = '';
        }
        $this->sql .= $separator . $value;
        $this->previous = $value;
        $this->previousIdentifier = $identifier;
    }

    /**
     * Returns the SQL assembled from emitted values.
     */
    public function toString(): string
    {
        return $this->sql;
    }
}
