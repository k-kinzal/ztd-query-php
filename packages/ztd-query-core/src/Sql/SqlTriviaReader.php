<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads what carries no meaning of its own: whitespace and comments.
 *
 * These are kept rather than dropped so that a statement can be written back
 * out exactly as it came in. A run of whitespace is one lexeme, and so is a
 * whole comment, however long it runs.
 */
final class SqlTriviaReader
{
    /**
     * Creates the trivia reader from what reads a block comment.
     *
     * @param SqlBlockCommentReader $blockComments What reads a block comment
     */
    public function __construct(
        private readonly SqlBlockCommentReader $blockComments = new SqlBlockCommentReader(),
    ) {
    }

    /**
     * Answers what trivia starts at an offset.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return SqlLexeme|null The trivia read there, or null when none starts there
     */
    public function readAt(string $sql, int $offset, SqlLexerProfile $profile): ?SqlLexeme
    {
        $length = strlen($sql);
        if (ctype_space($sql[$offset])) {
            for ($offset++; $offset < $length; $offset++) {
                if (!ctype_space($sql[$offset])) {
                    break;
                }
            }

            return new SqlLexeme(SqlTokenKind::Whitespace, $offset);
        }

        if ($profile->startsLineComment($sql, $offset)) {
            $lineEnd = strpos($sql, "\n", $offset);

            return new SqlLexeme(SqlTokenKind::Comment, $lineEnd === false ? $length : $lineEnd);
        }

        $blockCommentEnd = $this->blockComments->endAt($sql, $offset, $profile);

        return $blockCommentEnd === null ? null : new SqlLexeme(SqlTokenKind::Comment, $blockCommentEnd);
    }
}
