<?php

declare(strict_types=1);

namespace LemonParser\Scanner;

use LemonParser\Ast\Location;

/**
 * A position in the text being scanned, with the line and column it is at.
 *
 * @visibility root
 */
final class Cursor
{
    private int $offset = 0;

    private int $line = 1;

    private int $column = 1;

    /**
     * @param string $source The text
     */
    public function __construct(public readonly string $source)
    {
    }

    /**
     * Reports whether the end has been reached.
     *
     * @return bool True at the end
     */
    public function eof(): bool
    {
        return $this->offset >= strlen($this->source);
    }

    /**
     * Looks at a byte without moving.
     *
     * @param int $ahead How many bytes past the current one
     *
     * @return string The byte, or an empty string past the end
     */
    public function peek(int $ahead = 0): string
    {
        return $this->source[$this->offset + $ahead] ?? '';
    }

    /**
     * Answers the byte offset.
     *
     * @return int The offset from the start of the text
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Answers the position.
     *
     * @return Location Line and column of the current byte
     */
    public function location(): Location
    {
        return new Location($this->line, $this->column);
    }

    /**
     * Reports whether the text continues with a prefix.
     *
     * @param string $prefix What to look for
     *
     * @return bool True when it follows
     */
    public function startsWith(string $prefix): bool
    {
        return substr_compare($this->source, $prefix, $this->offset, strlen($prefix)) === 0;
    }

    /**
     * Moves past a number of bytes.
     *
     * @param int $length How many bytes, cut short at the end
     *
     * @return string The bytes moved past
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
     * Moves past a pattern when it matches here.
     *
     * @param string $pattern A regular expression body without delimiters
     *
     * @return string|null The match, or null when it does not match here
     */
    public function match(string $pattern): ?string
    {
        if (preg_match('~\G(?:' . str_replace('~', '\~', $pattern) . ')~A', $this->source, $match, 0, $this->offset) !== 1) {
            return null;
        }

        return $this->take(strlen($match[0]));
    }

    /**
     * Moves past everything up to and including a needle.
     *
     * @param string $needle What to look for
     *
     * @return string|null The text before the needle, or null when it is absent
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
