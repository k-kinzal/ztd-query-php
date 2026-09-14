<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Reads one part of a name out of the tokens a statement was written as.
 *
 * A name may be written bare or in whatever the dialect quotes identifiers
 * with, and a quoted one carries escapes that are not part of the name. Both
 * spellings answer the same name, and anything that is not a name at all
 * answers nothing.
 */
final class SqlIdentifierComponent
{
    /**
     * Reads the name written at a position, and says where reading it left off.
     *
     * @param list<SqlToken> $tokens Tokens to read, with nothing insignificant left in
     * @param int $index Position to read at
     * @param SqlLexerProfile $profile What the dialect quotes identifiers with
     *
     * @return array{string, int}|null The name and the position after it, or null where no name is written
     */
    public function at(array $tokens, int $index, SqlLexerProfile $profile): ?array
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word) {
            return [$token->text, $index + 1];
        }
        if ($token->kind === SqlTokenKind::QuotedIdentifier) {
            $name = $profile->quotedIdentifierValue($token->text);
            if ($name !== null) {
                return [$name, $index + 1];
            }
        }

        return null;
    }
}
