<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;

/**
 * Formats a DO wildcard refusal and its earlier default-database dependency.
 *
 * @visibility MySqlMemory
 */
final class EvaluationInput
{
    /**
     * Answers the native refusal without constructing or executing a replacement statement.
     */
    public function error(EvaluationWildcard $problem, Session $session): SqlError
    {
        if ($problem->requiresDatabase && $session->variables->database === '') {
            $error = QueryError::NoDatabase->error();
            return new SqlError($error->error, $error->getMessage(), following: $session->settings()->legacy() ? [] : [[$error->getCode(), $error->getMessage()]]);
        }
        $table = $problem->table;

        return $table === null ? QueryError::NoTablesUsed->error() : SchemaError::BadTable->error(($table->schema === null ? '' : $table->schema->value . '.') . $table->name->value);
    }
}
