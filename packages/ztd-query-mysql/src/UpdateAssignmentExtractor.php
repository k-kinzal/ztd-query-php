<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The update assignment extractor.
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
            $value = $this->value($assignment);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Answers what an assignment assigns, as written.
     *
     * Only the equals sign the statement itself wrote separates the column
     * from the value: one inside a subquery or a function call belongs to
     * that, not to the assignment.
     *
     * @param string $assignment One assignment, as written
     *
     * @return string|null The value assigned, or null where the text assigns nothing
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
