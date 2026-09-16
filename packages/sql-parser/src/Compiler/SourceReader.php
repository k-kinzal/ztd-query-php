<?php

declare(strict_types=1);

namespace SqlParser\Compiler;

/**
 * Reads a grammar source from left to right.
 *
 * @visibility root
 */
final class SourceReader
{
    private int $offset = 0;

    /**
     * @param string $source The whole source text
     */
    public function __construct(private readonly string $source)
    {
    }

    /**
     * Reports whether everything has been read.
     *
     * @return bool True at the end of the source
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
     * Consumes a number of bytes and answers them.
     *
     * @param int $length How many bytes to consume
     *
     * @return string The consumed bytes
     */
    public function take(int $length): string
    {
        $text = substr($this->source, $this->offset, $length);
        $this->offset += strlen($text);

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
        $this->offset += strlen($match[0]);

        return $match[0];
    }

    /**
     * Consumes everything up to and including a delimiter.
     *
     * @param string $needle Text that ends the run
     *
     * @return bool True when the delimiter was found, false when the end came first
     */
    public function skipPast(string $needle): bool
    {
        $end = strpos($this->source, $needle, $this->offset);
        if ($end === false) {
            $this->offset = strlen($this->source);

            return false;
        }
        $this->offset = $end + strlen($needle);

        return true;
    }

    /**
     * Answers the current byte offset.
     *
     * @return int Offset from the start of the source
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Answers the line the current position is on.
     *
     * @return int Line number counted from one
     */
    public function line(): int
    {
        return substr_count($this->source, "\n", 0, $this->offset) + 1;
    }
}
