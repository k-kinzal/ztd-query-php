<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class UpdateAssignmentExtractor
{
    /** @return list<string> */
    public function values(string $sql): array
    {
        $setClause = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(
            ['SET'],
            [['WHERE'], ['ORDER', 'BY'], ['LIMIT']],
        );
        if ($setClause === null) {
            return [];
        }

        $values = [];
        foreach (SqlTokenStream::tokenize($setClause, MySqlLexerProfile::create())->splitTopLevel() as $assignment) {
            $value = $this->value($assignment);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function value(string $assignment): ?string
    {
        foreach (SqlTokenStream::tokenize($assignment, MySqlLexerProfile::create())->significantTokens() as $token) {
            if ($token->kind === SqlTokenKind::Symbol && $token->text === '=' && $token->isTopLevel()) {
                return trim(substr($assignment, $token->endOffset()));
            }
        }

        return null;
    }
}
