<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Statement\Fact\Facts;

/**
 * Reports fixed semantic conditions when the column declaration is prepared.
 * An existing-destination shortcut may precede this stage.
 *
 * @visibility MySqlMemory
 */
final class DeclarationErrors
{
    /**
     * Preserves the conditions derived from the complete retained declaration.
     *
     * @throws \MySqlMemory\Error\SqlError When declared columns or keys contradict one another
     */
    public function check(Facts $facts): void
    {
        foreach ($facts->diagnostics as $problem) {
            $error = match (true) {
                $problem instanceof DuplicateColumn => SchemaError::DuplicateFieldName->error($problem->column->value),
                $problem instanceof UnknownKeyColumn => SchemaError::KeyColumnMissing->error($problem->column->value),
                $problem instanceof MultiplePrimaryKeys => SchemaError::MultiplePrimaryKey->error(),
                default => null,
            };
            if ($error !== null) {
                throw $error;
            }
        }
    }
}
