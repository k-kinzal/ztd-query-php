<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * A moving read position over grammar source text.
 *
 * Scanners share one cursor and advance it as they consume characters, so the
 * position is a collaborator they are handed rather than a field each of them
 * reaches into. That is what lets a single lexeme rule — a quoted literal, a
 * block comment, a brace-delimited action — be exercised on its own: give it a
 * cursor over the input it should read, and inspect where the cursor stopped.
 */
final class SourceCursor
{
    private readonly int $length;

    private int $offset = 0;

    /**
     * @param string $source Text to read
     */
    public function __construct(private readonly string $source)
    {
        $this->length = strlen($source);
    }

    /**
     * Reports whether the whole source has been consumed.
     *
     * @return bool True when no character remains
     */
    public function atEnd(): bool
    {
        return $this->offset >= $this->length;
    }

    /**
     * Reports the position the next read starts at.
     *
     * @return int Zero-based offset into the source
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Reads the character at the cursor without consuming it.
     *
     * @return string The current character, or an empty string at the end of the source
     */
    public function current(): string
    {
        return $this->atEnd() ? '' : $this->source[$this->offset];
    }

    /**
     * Reads the character after the cursor without consuming it.
     *
     * @return string|null The following character, or null when none remains
     */
    public function peek(): ?string
    {
        $next = $this->offset + 1;

        return $next >= $this->length ? null : $this->source[$next];
    }

    /**
     * Reports whether the cursor sits on the given text.
     *
     * @param string $prefix Text to compare against
     *
     * @return bool True when the source continues with that text
     */
    public function startsWith(string $prefix): bool
    {
        return str_starts_with(substr($this->source, $this->offset), $prefix);
    }

    /**
     * Moves the cursor forward without returning what was skipped.
     *
     * @param int $characters How many characters to skip
     */
    public function advance(int $characters = 1): void
    {
        $this->offset = min($this->offset + $characters, $this->length);
    }

    /**
     * Consumes any run of whitespace at the cursor.
     */
    public function skipWhitespace(): void
    {
        $this->takeWhile(static fn (string $character): bool => ctype_space($character));
    }

    /**
     * Consumes characters for as long as the predicate accepts them.
     *
     * @param callable(string): bool $accepts Decides whether a character belongs to the run
     *
     * @return string The consumed run, empty when the first character is rejected
     */
    public function takeWhile(callable $accepts): string
    {
        $start = $this->offset;
        while ($this->offset < $this->length && $accepts($this->source[$this->offset])) {
            ++$this->offset;
        }

        return substr($this->source, $start, $this->offset - $start);
    }

    /**
     * Consumes text up to the terminator, and the terminator with it.
     *
     * An absent terminator consumes the rest of the source, which is how an
     * unterminated prologue or comment is reported as its content instead of
     * as a failure.
     *
     * @param string $terminator Text that closes the run
     *
     * @return string The text before the terminator
     */
    public function takeUntil(string $terminator): string
    {
        $start = $this->offset;
        $end = strpos($this->source, $terminator, $this->offset);
        if ($end === false) {
            $this->offset = $this->length;

            return substr($this->source, $start);
        }

        $this->offset = $end + strlen($terminator);

        return substr($this->source, $start, $end - $start);
    }

    /**
     * Consumes a quoted run, starting at its opening quote.
     *
     * A backslash escapes the character after it, so an escaped quote does not
     * close the run. An unterminated run consumes the rest of the source.
     *
     * @param string $quote The quote character that opens and closes the run
     *
     * @return string The unescaped text between the quotes
     */
    public function takeQuoted(string $quote): string
    {
        $this->advance();
        $taken = '';

        while ($this->offset < $this->length) {
            $character = $this->source[$this->offset];

            if ($character === '\\') {
                ++$this->offset;
                if ($this->offset < $this->length) {
                    $taken .= $this->source[$this->offset];
                    ++$this->offset;
                }
                continue;
            }

            ++$this->offset;
            if ($character === $quote) {
                break;
            }

            $taken .= $character;
        }

        return $taken;
    }

    /**
     * Reads back text the cursor has already passed over.
     *
     * A lexeme whose body is defined by delimiters — a brace-delimited action,
     * for instance — is recognised by walking to its closing delimiter and then
     * asking for the text in between, rather than by accumulating characters it
     * may still turn out not to own.
     *
     * @param int $start Offset the text begins at
     * @param int $end Offset the text ends before
     *
     * @return string The text between the two offsets, empty when they cross
     */
    public function textBetween(int $start, int $end): string
    {
        return $end <= $start ? '' : substr($this->source, $start, $end - $start);
    }

    /**
     * Consumes everything that is left.
     *
     * @return string The remaining source, empty when the cursor is at the end
     */
    public function takeRest(): string
    {
        $rest = substr($this->source, $this->offset);
        $this->offset = $this->length;

        return $rest;
    }
}
