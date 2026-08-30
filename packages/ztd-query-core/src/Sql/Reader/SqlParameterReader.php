<?php

declare(strict_types=1);

namespace ZtdQuery\Sql\Reader;

use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Reads a placeholder standing in for a value the driver will bind.
 *
 * A placeholder is written either by position, where the whole of it is one
 * run of bytes the profile measures, or by name, where a prefix is followed
 * by a name that may itself be spelled in parts. Which prefixes, separators
 * and suffixes a dialect allows are all asked of the profile.
 */
final class SqlParameterReader
{
    /**
     * Answers what placeholder starts at an offset.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return SqlLexeme|null The placeholder read there, or null when none starts there
     */
    public function readAt(string $sql, int $offset, SqlLexerProfile $profile): ?SqlLexeme
    {
        $positionalLength = $profile->positionalParameterLengthAt($sql, $offset);
        if ($positionalLength > 0) {
            return new SqlLexeme(SqlTokenKind::Parameter, $offset + $positionalLength);
        }

        $prefix = $profile->namedParameterPrefixAt($sql, $offset);
        if ($prefix === null || !$profile->isIdentifierStart($sql[$offset + strlen($prefix)] ?? '')) {
            return null;
        }

        $offset = $this->endOfName($sql, $offset + strlen($prefix), $prefix, $profile);

        return new SqlLexeme(
            SqlTokenKind::Parameter,
            $offset + $profile->parameterSuffixLength($prefix, $sql, $offset),
        );
    }

    /**
     * Answers where the name of a placeholder ends.
     *
     * A name may be spelled in parts, joined by whatever the dialect allows;
     * a separator only joins when a further name follows it, so a trailing
     * one is left to be read as something else.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where the name starts
     * @param string $prefix The prefix the placeholder was written with
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return int The offset just past the name
     */
    public function endOfName(string $sql, int $offset, string $prefix, SqlLexerProfile $profile): int
    {
        $length = strlen($sql);
        while ($offset < $length) {
            if ($profile->isIdentifierPart($sql[$offset])) {
                $offset++;
                continue;
            }
            $separator = $profile->parameterNameSeparatorAt($prefix, $sql, $offset);
            if ($separator === null
                || !$profile->isIdentifierStart($sql[$offset + strlen($separator)] ?? '')
            ) {
                break;
            }
            $offset += strlen($separator);
        }

        return $offset;
    }
}
