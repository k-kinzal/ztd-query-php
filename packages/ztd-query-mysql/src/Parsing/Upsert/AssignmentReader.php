<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Upsert;

use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Assignment Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class AssignmentReader
{
    /**
     * @return array{column: string, value: string}|null
     */
    public function assignment(string $assignment): ?array
    {
        $tokens = SqlTokenStream::tokenize($assignment, MySqlLexerProfile::create())->significantTokens();
        foreach ($tokens as $index => $token) {
            if ($token->kind !== SqlTokenKind::Symbol || $token->text !== '=' || !$token->isTopLevel()) {
                continue;
            }
            $column = $this->lastIdentifier(array_slice($tokens, 0, $index));
            $value = trim(substr($assignment, $token->endOffset()));
            if ($column === null || $value === '') {
                return null;
            }

            return ['column' => $column, 'value' => $value];
        }

        return null;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    public function lastIdentifier(array $tokens): ?string
    {
        $token = array_pop($tokens);
        return $token !== null
            ? SqlTokenStream::tokenize($token->text, MySqlLexerProfile::create())->identifierAt()['name'] ?? null
            : null;
    }
}
