<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads a statement into the lexemes it is written as, losing nothing.
 *
 * Every byte of the statement ends up in exactly one token, whitespace and
 * comments included, so that the statement can always be written back out
 * unchanged. Nothing here knows any SQL: which quotes open a string, what
 * begins a comment and what may spell an identifier are all asked of the
 * profile, so one reader serves every dialect.
 */
final class SqlTokenScanner
{
    /**
     * Reads a statement into its lexemes.
     *
     * @param string $sql The statement, as written
     * @param SqlLexerProfile $profile What the dialect spells things with
     *
     * @return list<SqlToken> Every lexeme, in the order they were written
     */
    public function scan(string $sql, SqlLexerProfile $profile): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;
        $depth = 0;
        $bracketDepth = 0;

        while ($offset < $length) {
            $start = $offset;
            $char = $sql[$offset];
            if (ctype_space($char)) {
                while ($offset < $length && ctype_space($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Whitespace, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->startsLineComment($sql, $offset)) {
                $lineEnd = strpos($sql, "\n", $offset);
                $offset = $lineEnd === false ? $length : $lineEnd;
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $blockComment = $profile->blockCommentAt($sql, $offset);
            if ($blockComment !== null) {
                [$opening, $closing] = $blockComment;
                $offset += strlen($opening);
                $commentDepth = 1;
                while ($commentDepth > 0) {
                    if (!isset($sql[$offset])) {
                        break;
                    }
                    if ($profile->supportsNestedBlockComments()
                        && substr_compare($sql, $opening, $offset, strlen($opening)) === 0
                    ) {
                        $commentDepth++;
                        $offset += strlen($opening);
                        continue;
                    }
                    if (substr_compare($sql, $closing, $offset, strlen($closing)) === 0) {
                        $commentDepth--;
                        $offset += strlen($closing);
                        continue;
                    }
                    $offset++;
                }
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Comment, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $stringQuoteClosing = $profile->stringQuoteClosing($char);
            if ($stringQuoteClosing !== null) {
                $offset = $this->endOfDelimited(
                    $sql,
                    $offset,
                    $char,
                    $stringQuoteClosing,
                    $profile->stringUsesBackslashEscapes($sql, $offset),
                );
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $identifierQuoteClosing = $profile->identifierQuoteClosing($char);
            if ($identifierQuoteClosing !== null) {
                $offset = $this->endOfDelimited($sql, $offset, $char, $identifierQuoteClosing, false);
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::QuotedIdentifier, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $dollarQuoteDelimiter = $profile->dollarQuoteDelimiterAt($sql, $offset);
            if ($dollarQuoteDelimiter !== null) {
                $delimiterLength = strlen($dollarQuoteDelimiter);
                $end = strpos($sql, $dollarQuoteDelimiter, $offset + $delimiterLength);
                $offset = $end === false ? $length : $end + $delimiterLength;
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::String, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $positionalParameterLength = $profile->positionalParameterLengthAt($sql, $offset);
            if ($positionalParameterLength > 0) {
                $offset += $positionalParameterLength;
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $parameterPrefix = $profile->namedParameterPrefixAt($sql, $offset);
            if ($parameterPrefix !== null
                && $profile->isIdentifierStart($sql[$offset + strlen($parameterPrefix)] ?? '')
            ) {
                $offset += strlen($parameterPrefix);
                while ($offset < $length) {
                    if ($profile->isIdentifierPart($sql[$offset])) {
                        $offset++;
                        continue;
                    }
                    $separator = $profile->parameterNameSeparatorAt($parameterPrefix, $sql, $offset);
                    if ($separator === null
                        || !$profile->isIdentifierStart($sql[$offset + strlen($separator)] ?? '')
                    ) {
                        break;
                    }
                    $offset += strlen($separator);
                }
                $offset += $profile->parameterSuffixLength($parameterPrefix, $sql, $offset);
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Parameter, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->isIdentifierStart($char)) {
                $offset++;
                while ($offset < $length && $profile->isIdentifierPart($sql[$offset])) {
                    $offset++;
                }
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Word, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            $numberLength = $profile->numberLengthAt($sql, $offset);
            if ($numberLength > 0) {
                $offset += $numberLength;
                $tokens[] = SqlToken::slice($sql, SqlTokenKind::Number, $start, $offset, $depth, $bracketDepth);
                continue;
            }

            if ($profile->isNestingClosing($char)) {
                $depth = max(0, $depth - 1);
            } elseif ($profile->isBracketClosing($char)) {
                $bracketDepth = max(0, $bracketDepth - 1);
            }
            $offset++;
            $tokens[] = SqlToken::slice($sql, SqlTokenKind::Symbol, $start, $offset, $depth, $bracketDepth);
            if ($profile->isNestingOpening($char)) {
                $depth++;
            } elseif ($profile->isBracketOpening($char)) {
                $bracketDepth++;
            }
        }

        return $tokens;
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
