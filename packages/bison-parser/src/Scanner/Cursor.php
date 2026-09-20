<?php

declare(strict_types=1);

namespace BisonParser\Scanner;

use BisonParser\Ast\Location;

/**
 * Reads a grammar file byte by byte, keeping the line and column.
 *
 * @visibility root
 */
final class Cursor
{
    private int $offset = 0;

    private int $line = 1;

    private int $column = 1;

    /**
     * @param string $source The whole file
     */
    public function __construct(public readonly string $source)
    {
    }

    /**
     * Reports whether everything has been read.
     *
     * @return bool True at the end of the file
     */
    public function eof(): bool
    {
        return $this->offset >= strlen($this->source);
    }

    /**
     * Answers the byte at a distance ahead without consuming it.
     *
     * @param int $ahead Distance from the current position
     *
     * @return string The byte, or an empty string past the end
     */
    public function peek(int $ahead = 0): string
    {
        return $this->source[$this->offset + $ahead] ?? '';
    }

    /**
     * Answers the current byte offset.
     *
     * @return int Offset from the start of the file
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Answers the current position as a line and column.
     *
     * @return Location The position
     */
    public function location(): Location
    {
        return new Location($this->line, $this->column);
    }

    /**
     * Reports whether the unread text starts with a string.
     *
     * @param string $prefix Text to look for
     *
     * @return bool True when the next bytes spell the prefix
     */
    public function startsWith(string $prefix): bool
    {
        return substr_compare($this->source, $prefix, $this->offset, strlen($prefix)) === 0;
    }

    /**
     * Consumes a number of bytes and answers them, counting the lines they hold.
     *
     * @param int $length How many bytes to consume
     *
     * @return string The consumed bytes
     */
    public function take(int $length): string
    {
        $text = substr($this->source, $this->offset, $length);
        $this->offset += strlen($text);
        $newlines = substr_count($text, "\n");
        if ($newlines > 0) {
            $this->line += $newlines;
            $this->column = strlen($text) - (int) strrpos($text, "\n");
        } else {
            $this->column += strlen($text);
        }

        return $text;
    }

    /**
     * Consumes what a pattern matches at the current position.
     *
     * @param string $pattern Regular expression without anchors or delimiters
     *
     * @return string|null The matched text, or null when nothing matches here
     */
    public function match(string $pattern): ?string
    {
        if (preg_match('~\G(?:' . str_replace('~', '\~', $pattern) . ')~A', $this->source, $match, 0, $this->offset) !== 1) {
            return null;
        }

        return $this->take(strlen($match[0]));
    }

    /**
     * Reports whether a pattern matches at the current position, without consuming it.
     *
     * @param string $pattern Regular expression without anchors or delimiters
     *
     * @return bool True when it matches here
     */
    public function lookingAt(string $pattern): bool
    {
        return preg_match('~\G(?:' . str_replace('~', '\~', $pattern) . ')~A', $this->source, $match, 0, $this->offset) === 1;
    }

    /**
     * Consumes everything up to and including a delimiter.
     *
     * @param string $needle Text that ends the run
     *
     * @return string|null The consumed text without the delimiter, or null when the file ends first
     */
    public function takeUntil(string $needle): ?string
    {
        $end = strpos($this->source, $needle, $this->offset);
        if ($end === false) {
            return null;
        }
        $text = $this->take($end - $this->offset);
        $this->take(strlen($needle));

        return $text;
    }
}
