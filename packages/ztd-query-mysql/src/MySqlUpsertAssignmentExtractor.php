<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class MySqlUpsertAssignmentExtractor
{
    /** @return array<string, string> */
    public function extract(string $sql): array
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $clause = $stream->topLevelClause(['ON', 'DUPLICATE', 'KEY', 'UPDATE']);
        if ($clause === null) {
            return [];
        }

        $assignments = [];
        foreach (SqlTokenStream::tokenize($clause, MySqlLexerProfile::create())->splitTopLevel() as $assignment) {
            $parts = $this->assignment($assignment);
            if ($parts !== null) {
                $assignments[$parts['column']] = $parts['value'];
            }
        }

        return $assignments;
    }

    public function incomingAlias(string $sql): ?string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $clause = $stream->topLevelClauseAfter(
            ['VALUES'],
            ['AS'],
        );
        if ($clause === null) {
            return null;
        }

        return SqlTokenStream::tokenize($clause, MySqlLexerProfile::create())->identifierAt()['name'] ?? null;
    }

    /** @return array{column: string, value: string}|null */
    private function assignment(string $assignment): ?array
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

    /** @param list<SqlToken> $tokens */
    private function lastIdentifier(array $tokens): ?string
    {
        $token = array_pop($tokens);
        return $token !== null
            ? SqlTokenStream::tokenize($token->text, MySqlLexerProfile::create())->identifierAt()['name'] ?? null
            : null;
    }

}
