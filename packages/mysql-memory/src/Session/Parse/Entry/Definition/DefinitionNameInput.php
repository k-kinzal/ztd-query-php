<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName;

/**
 * Reports the first mismatched component of a legacy definition qualifier.
 *
 * Database selection precedes table-name comparison, even for a missing table
 * or an unexecuted stored instruction. Observed on MySQL 5.6.51 and 5.7.44.
 *
 * @visibility MySqlMemory
 */
final class DefinitionNameInput
{
    /**
     * Resolves the omitted database without reading any SQL text or parser node.
     */
    public function error(MismatchedDefinitionName $problem, Session $session): SqlError
    {
        $database = $problem->target->schema->value ?? $session->variables->database;
        if ($database === '') {
            return QueryError::NoDatabase->error();
        }
        $qualifier = $problem->name->table;
        return $qualifier?->schema !== null && $qualifier->schema->value !== $database
            ? SchemaError::WrongDatabaseName->error($qualifier->schema->value)
            : SchemaError::WrongTableName->error($qualifier->name->value ?? '');
    }
}
