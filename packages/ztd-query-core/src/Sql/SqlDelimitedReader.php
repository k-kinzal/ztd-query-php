<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads what a pair of delimiters closes: strings and quoted identifiers.
 *
 * Which byte opens a run, which one closes it, and whether a backslash
 * escapes inside it are all the dialect's business, so they are asked of the
 * profile. A dollar-quoted string is closed by the same tag that opened it
 * and nothing inside it escapes, so it is read on its own terms.
 */
final class SqlDelimitedReader
{
    /**
     * Answers what delimited run starts at an offset.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return SqlLexeme|null The run read there, or null when none starts there
     */
    public function readAt(string $sql, int $offset, SqlLexerProfile $profile): ?SqlLexeme
    {
        $opening = $sql[$offset];

        $stringClosing = $profile->stringQuoteClosing($opening);
        if ($stringClosing !== null) {
            $backslashEscapes = $profile->stringUsesBackslashEscapes($sql, $offset);

            return new SqlLexeme(
                SqlTokenKind::String,
                $this->endOfDelimited($sql, $offset, $opening, $stringClosing, $backslashEscapes),
            );
        }

        $identifierClosing = $profile->identifierQuoteClosing($opening);
        if ($identifierClosing !== null) {
            return new SqlLexeme(
                SqlTokenKind::QuotedIdentifier,
                $this->endOfDelimited($sql, $offset, $opening, $identifierClosing, false),
            );
        }

        $tag = $profile->dollarQuoteDelimiterAt($sql, $offset);
        if ($tag === null) {
            return null;
        }

        $closing = strpos($sql, $tag, $offset + strlen($tag));

        return new SqlLexeme(
            SqlTokenKind::String,
            $closing === false ? strlen($sql) : $closing + strlen($tag),
        );
    }

    /**
     * Answers where a run closed by a delimiter ends.
     *
     * A doubled closing delimiter is how SQL writes the delimiter itself, so
     * it does not close the run. A run left unclosed at the end of the
     * statement ends there, because there is nothing further to read.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where the opening delimiter is
     * @param string $opening The delimiter that opened the run
     * @param string $closing The delimiter that will close it
     * @param bool $backslashEscapes Whether a backslash escapes the byte after it
     *
     * @return int The offset just past the closing delimiter
     */
    public function endOfDelimited(
        string $sql,
        int $offset,
        string $opening,
        string $closing,
        bool $backslashEscapes,
    ): int {
        $offset += strlen($opening);
        while (isset($sql[$offset])) {
            if (substr_compare($sql, $closing, $offset, strlen($closing)) === 0) {
                if (substr_compare($sql, $closing . $closing, $offset, strlen($closing) * 2) === 0) {
                    $offset += strlen($closing) * 2;
                    continue;
                }

                return $offset + strlen($closing);
            }
            if ($backslashEscapes && $sql[$offset] === '\\') {
                $offset += 2;
                continue;
            }
            $offset++;
        }

        return strlen($sql);
    }
}
