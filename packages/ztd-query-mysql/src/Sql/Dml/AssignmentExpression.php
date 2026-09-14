<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Sql\Dml;

use ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Assignment Value.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class AssignmentExpression
{
    /**
     * Value for the supplied MySQL input.
     */
    public function value(string $assignment): ?string
    {
        foreach (SqlTokenStream::tokenize($assignment, MySqlLexerProfile::create())->significantTokens() as $token) {
            if ($token->kind === SqlTokenKind::Symbol && $token->text === '=' && $token->isTopLevel()) {
                return trim(substr($assignment, $token->endOffset()));
            }
        }

        return null;
    }
}
