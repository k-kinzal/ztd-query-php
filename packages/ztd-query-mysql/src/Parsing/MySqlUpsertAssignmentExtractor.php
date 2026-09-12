<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Upsert Assignment Extractor contract for MySQL.
 */
final class MySqlUpsertAssignmentExtractor
{
    /**
     * @return array<string, string>
     */
    public function extract(string $sql): array
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $clause = $stream->topLevelClause(['ON', 'DUPLICATE', 'KEY', 'UPDATE']);
        if ($clause === null) {
            return [];
        }

        $assignments = [];
        foreach (SqlTokenStream::tokenize($clause, MySqlLexerProfile::create())->splitTopLevel() as $assignment) {
            $parts = (new Parsing\Upsert\AssignmentReader())->assignment($assignment);
            if ($parts !== null) {
                $assignments[$parts['column']] = $parts['value'];
            }
        }

        return $assignments;
    }

    /**
     * Incoming Alias for the supplied MySQL input.
     */
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

}
