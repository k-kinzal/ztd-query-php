<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * Reads SQL text from left to right, one token at a time.
 *
 * @visibility root
 */
final class Cursor
{
    private int $offset = 0;

    /**
     * @param string $source The whole SQL text
     */
    public function __construct(public readonly string $source)
    {
    }

    /**
     * Reports whether everything has been read.
     *
     * @return bool True at the end of the text
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
     * @return int Offset from the start of the text
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Moves to a byte offset.
     *
     * @param int $offset Offset to move to
     */
    public function seek(int $offset): void
    {
        $this->offset = $offset;
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
     * Reports whether the unread text starts with a string, regardless of case.
     *
     * @param string $prefix Text to look for
     *
     * @return bool True when the next bytes spell the prefix in any case
     */
    public function startsWithIgnoringCase(string $prefix): bool
    {
        return substr_compare($this->source, $prefix, $this->offset, strlen($prefix), true) === 0;
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
     * Consumes everything up to and including a delimiter, or to the end when it is missing.
     *
     * @param string $needle Text that ends the run
     *
     * @return bool True when the delimiter was found
     */
    public function skipPastOrEnd(string $needle): bool
    {
        $end = strpos($this->source, $needle, $this->offset);
        $this->offset = $end === false ? strlen($this->source) : $end + strlen($needle);

        return $end !== false;
    }

    /**
     * Consumes a quoted run whose quote escapes itself by doubling, and optionally by a backslash.
     *
     * @param string $quote The quote character the run opens with
     * @param bool $backslashEscapes Whether a backslash escapes the next byte
     *
     * @return string|null The run including both quotes, or null when it never closes
     */
    public function takeQuoted(string $quote, bool $backslashEscapes = false): ?string
    {
        $start = $this->offset;
        $length = strlen($this->source);
        $position = $start + 1;
        while ($position < $length) {
            $character = $this->source[$position];
            if ($backslashEscapes && $character === '\\') {
                $position += 2;
                continue;
            }
            if ($character !== $quote) {
                $position++;
                continue;
            }
            if (($this->source[$position + 1] ?? '') === $quote) {
                $position += 2;
                continue;
            }
            $this->offset = $position + 1;

            return substr($this->source, $start, $this->offset - $start);
        }

        return null;
    }
}
