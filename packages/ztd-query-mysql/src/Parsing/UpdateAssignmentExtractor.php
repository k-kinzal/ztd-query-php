<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the Update Assignment Extractor contract for MySQL.
 */
final class UpdateAssignmentExtractor
{
    /**
     * @return list<string>
     */
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
            $value = (new Parsing\AssignmentExpression())->value($assignment);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

}
