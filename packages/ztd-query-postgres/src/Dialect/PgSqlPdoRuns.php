<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Dialect;

/**
 * The runs of a statement that are carried over exactly as they were written.
 *
 * PDO reads a statement for placeholders, and everything it finds inside a
 * comment, a quoted name, a string or a dollar-quoted body is not one. Saying
 * where each of those runs ends is what lets the rest of the statement be
 * read as the operators and words it is.
 */
final class PgSqlPdoRuns
{
    private const DIGITS = '0123456789';

    private const NAME_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_0123456789$';

    /**
     * Answers where the whitespace or comment starting here ends.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where to look
     *
     * @return int|null The offset just past it, or null where neither starts there
     */
    public function triviaEnd(string $sql, int $offset): ?int
    {
        $length = strlen($sql);
        if (ctype_space($sql[$offset])) {
            return $offset + 1;
        }
        if (substr($sql, $offset, 2) === '--') {
            return $offset + strcspn($sql, "\r\n", $offset);
        }
        if (substr($sql, $offset, 2) !== '/*') {
            return null;
        }

        $scan = $offset;
        $depth = 0;
        for ($step = 0; $step < $length; $step++) {
            $open = strpos($sql, '/*', $scan);
            $close = strpos($sql, '*/', $scan);
            $marker = min($open === false ? $length : $open, $close === false ? $length : $close);
            if ($marker === $length) {
                return $length;
            }
            $scan = $marker + 2;
            $depth += substr($sql, $marker, 2) === '/*' ? 1 : -1;
            if ($depth === 0) {
                return $scan;
            }
        }

        return $length;
    }

    /**
     * Answers where the quoted or dollar-quoted run starting here ends.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where to look
     *
     * @return int|null The offset just past it, or null where none starts there
     */
    public function valueEnd(string $sql, int $offset): ?int
    {
        $character = $sql[$offset];
        if ($character === "'" || $character === '"') {
            return $this->quotedEnd($sql, $offset, $character);
        }
        if ($character !== '$') {
            return null;
        }

        $delimiter = PgSqlPdoPlaceholderEscaper::dollarQuoteDelimiter($sql, $offset);
        if ($delimiter === null) {
            return null;
        }
        $closing = strpos($sql, $delimiter, $offset + strlen($delimiter));

        return $closing === false ? strlen($sql) : $closing + strlen($delimiter);
    }

    /**
     * Answers where the run a quote opened ends.
     *
     * A doubled quote is how the quote itself is written and does not close
     * the run; inside one of PostgreSQL's escape strings a backslash makes
     * the byte after it stand for itself, quote included.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where the opening quote is
     * @param string $quote The quote that opened it
     *
     * @return int The offset just past the closing quote
     */
    public function quotedEnd(string $sql, int $offset, string $quote): int
    {
        $length = strlen($sql);
        $escapesWithBackslash = $quote === "'" && PgSqlPdoPlaceholderEscaper::isEscapeStringStart($sql, $offset);
        for ($offset++; $offset < $length; $offset++) {
            if ($escapesWithBackslash && $sql[$offset] === '\\') {
                $offset++;
                continue;
            }
            if ($sql[$offset] !== $quote) {
                continue;
            }
            if (substr($sql, $offset, 2) === $quote . $quote) {
                $offset++;
                continue;
            }

            return $offset + 1;
        }

        return $length;
    }

    /**
     * Answers where the bare word starting here ends.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where to look
     *
     * @return int|null The offset just past it, or null where no word starts there
     */
    public function wordEnd(string $sql, int $offset): ?int
    {
        if (!PgSqlPdoPlaceholderEscaper::isIdentifierStart($sql[$offset])) {
            return null;
        }

        return $offset + strspn($sql, self::NAME_CHARACTERS, $offset);
    }

    /**
     * Answers where the number starting here ends.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where to look
     *
     * @return int|null The offset just past it, or null where no number starts there
     */
    public function numberEnd(string $sql, int $offset): ?int
    {
        if (!ctype_digit($sql[$offset])) {
            return null;
        }

        return $offset + strspn($sql, self::DIGITS . '.', $offset);
    }
}
