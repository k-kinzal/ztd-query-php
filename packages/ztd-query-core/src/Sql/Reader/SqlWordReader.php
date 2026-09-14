<?php

declare(strict_types=1);

namespace ZtdQuery\Sql\Reader;

use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Reads what is written plainly: a bare word or a number.
 *
 * A bare word is a keyword or an unquoted identifier; nothing here tells
 * them apart, because which words are keywords is not a question the reader
 * has to answer. How far a number runs is the dialect's business, since
 * exponents, hex and separators differ, so the profile measures it.
 */
final class SqlWordReader
{
    /**
     * Answers what plain lexeme starts at an offset.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return SqlLexeme|null The lexeme read there, or null when none starts there
     */
    public function readAt(string $sql, int $offset, SqlLexerProfile $profile): ?SqlLexeme
    {
        if ($profile->isIdentifierStart($sql[$offset])) {
            $length = strlen($sql);
            for ($offset++; $offset < $length; $offset++) {
                if (!$profile->isIdentifierPart($sql[$offset])) {
                    break;
                }
            }

            return new SqlLexeme(SqlTokenKind::Word, $offset);
        }

        $numberLength = $profile->numberLengthAt($sql, $offset);

        return $numberLength > 0 ? new SqlLexeme(SqlTokenKind::Number, $offset + $numberLength) : null;
    }
}
