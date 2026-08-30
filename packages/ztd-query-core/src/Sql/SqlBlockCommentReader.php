<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads a block comment, opening to closing.
 *
 * Which pair of delimiters writes a block comment, and whether one comment
 * may hold another, are both the dialect's business, so they are asked of
 * the profile. A comment left unclosed runs to the end of the statement,
 * because there is nothing further to close it with.
 */
final class SqlBlockCommentReader
{
    /**
     * Answers where the block comment starting at an offset ends.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return int|null The offset just past the closing delimiter, or null when no comment starts there
     */
    public function endAt(string $sql, int $offset, SqlLexerProfile $profile): ?int
    {
        $delimiters = $profile->blockCommentAt($sql, $offset);
        if ($delimiters === null) {
            return null;
        }

        [$opening, $closing] = $delimiters;
        $offset += strlen($opening);
        $depth = 1;
        while ($depth > 0 && isset($sql[$offset])) {
            if ($profile->supportsNestedBlockComments()
                && substr_compare($sql, $opening, $offset, strlen($opening)) === 0
            ) {
                $depth++;
                $offset += strlen($opening);
                continue;
            }
            if (substr_compare($sql, $closing, $offset, strlen($closing)) === 0) {
                $depth--;
                $offset += strlen($closing);
                continue;
            }
            $offset++;
        }

        return $offset;
    }
}
