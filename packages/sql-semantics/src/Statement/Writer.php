<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * Separates SQL values without changing quoted content or lexical adjacency.
 *
 * Values are separated by one space unless the language requires them to touch:
 * a name and the `.` or `(` after it, and a prefix value such as a variable
 * marker and what it introduces. Comments are written where the value they
 * precede is written, and a line comment ends its line.
 *
 * @visibility public
 * @example Rendering an SQL fragment
 *     $render = static fn (\SqlSemantics\Statement\Element $element): string => \SqlSemantics\Statement\Writer::render($element);
 *     $render instanceof \Closure // => true
 * @example Keeping a prefix value attached to what follows it
 *     $writer = new \SqlSemantics\Statement\Writer();
 *     $writer->append('@', prefix: true);
 *     $writer->append('name', true);
 *     $writer->toString() // => '@name'
 */
final class Writer
{
    private string $sql = '';
    private string $previous = '';
    private bool $previousIdentifier = false;
    private bool $previousPrefix = false;
    private ?bool $previousLineComment = null;

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
     *
     * @param bool $identifier Whether the value is a name, which `.` follows directly and `(` does not
     * @param bool $prefix Whether the next value must follow this one without a separator
     */
    public function append(string $value, bool $identifier = false, bool $prefix = false): void
    {
        if ($value === '') {
            return;
        }
        $separator = ' ';
        if ($this->sql === '') {
            $separator = '';
        } elseif ($this->previousLineComment !== null) {
            $separator = $this->previousLineComment ? "\n" : ' ';
        } elseif ($this->previousPrefix || $this->previous === '.' || ($value === '(' && !$this->previousIdentifier) || ($value === '.' && $this->previousIdentifier)) {
            $separator = '';
        }
        $this->sql .= $separator . $value;
        $this->previous = $value;
        $this->previousIdentifier = $identifier;
        $this->previousPrefix = $prefix;
        $this->previousLineComment = null;
    }

    /**
     * Emits the comments written before the symbol at a position of a value.
     */
    public function comments(Comments $comments, int $position): void
    {
        foreach ($comments->before($position) as $comment) {
            $this->sql .= ($this->sql === '' ? '' : ($this->previousLineComment === true ? "\n" : ' ')) . $comment;
            $this->previous = '';
            $this->previousIdentifier = false;
            $this->previousPrefix = false;
            $this->previousLineComment = str_starts_with($comment, '--') || str_starts_with($comment, '#');
        }
    }

    /**
     * Returns the SQL assembled from emitted values.
     */
    public function toString(): string
    {
        return $this->sql;
    }
}
