<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong;

/**
 * Formats oversized decoded names without changing their retained values.
 * Native database and table namespaces reject more than 192 UTF-8 bytes as
 * an incorrect object name; shorter spellings exceeding 64 characters report
 * an oversized identifier. Diagnostics display at most 100 bytes, up to NUL.
 * Observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class IdentifierErrors
{
    /**
     * Preserves the namespace-specific refusal and the release's UTF-8 display boundary.
     */
    public function length(NameTooLong $problem, bool $legacy): SqlError
    {
        $family = strlen($problem->name->value) <= 192 ? SchemaError::TooLongIdentifier : match ($problem->kind) {
            ObjectNameKind::Database => SchemaError::WrongDatabaseName,
            ObjectNameKind::Table => SchemaError::WrongTableName,
            ObjectNameKind::Server, null => SchemaError::TooLongIdentifier,
        };
        return $family->error((new DiagnosticText())->bytes(explode("\0", $problem->name->value)[0], 100, $legacy));
    }
}
