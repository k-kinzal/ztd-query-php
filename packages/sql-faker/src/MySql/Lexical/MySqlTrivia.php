<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Lexical;

use SqlFaker\Grammar\Lexical\LexicalException;

/**
 * Skips what a MySQL statement carries but does not mean.
 *
 * Whitespace and comments separate tokens without being any, and a quoted run
 * is passed over whole because nothing inside it is read as SQL. Both are
 * asked for by offset and answer by moving it, so the reader that owns the
 * statement stays the one deciding what to do next.
 */
final class MySqlTrivia
{
    /**
     * Consumes whitespace or one comment.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return bool True when something was skipped
     *
     * @throws LexicalException When a block comment never closes
     */
    public function skipTrivia(string $sql, int &$offset): bool
    {
        if (preg_match('/\G\s+/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return true;
        }

        if (substr($sql, $offset, 2) === '/*') {
            $end = strpos($sql, '*/', $offset + 2);
            if ($end === false) {
                throw LexicalException::unterminatedBlockComment('MySQL');
            }
            $offset = $end + 2;

            return true;
        }

        $opensLineComment = $sql[$offset] === '#'
            || (substr($sql, $offset, 2) === '--' && preg_match('/\s/', $sql[$offset + 2] ?? '') === 1);
        if (!$opensLineComment) {
            return false;
        }

        $end = strpos($sql, "\n", $offset + 1);
        $offset = $end === false ? strlen($sql) : $end + 1;

        return true;
    }

    /**
     * Consumes a quoted run, doubling being the way a quote escapes itself.
     *
     * @param string $sql Text being read
     * @param int $offset Offset of the opening quote; moved past the closing one
     * @param string $quote The quote character
     *
     * @throws LexicalException When the run never closes
     */
    public function skipQuoted(string $sql, int &$offset, string $quote): void
    {
        $length = strlen($sql);
        ++$offset;

        while ($offset < $length) {
            if ($sql[$offset] !== $quote) {
                ++$offset;
                continue;
            }
            if (($sql[$offset + 1] ?? null) === $quote) {
                $offset += 2;
                continue;
            }
            ++$offset;

            return;
        }

        throw LexicalException::unterminatedQuotedToken('MySQL', $sql);
    }
}
