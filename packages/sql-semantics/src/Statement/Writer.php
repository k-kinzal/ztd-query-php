<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * Separates SQL values without changing quoted content or lexical adjacency.
 *
 * @visibility public
 * @example Rendering an SQL fragment
 *     $render = static fn (\SqlSemantics\Statement\Element $element): string => \SqlSemantics\Statement\Writer::render($element);
 *     $render instanceof \Closure // => true
 */
final class Writer
{
    private string $sql = '';
    private string $previous = '';
    private bool $previousIdentifier = false;

    /**
     * Writes an individual SQL fragment or complete command from its structure.
     */
    public static function render(Element $element): string
    {
        $writer = new self();
        $element->write($writer);

        return $writer->toString();
    }

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
