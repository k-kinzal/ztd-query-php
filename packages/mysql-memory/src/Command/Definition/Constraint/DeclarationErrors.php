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
    /**
     * Checks the complete proposed layout before constructing an ordinary table statement.
     * ALTER inputs may be coherent by themselves while contradicting the current table.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement> $elements The proposed columns and keys after renames and drops
     * @throws \MySqlMemory\Error\SqlError When the proposed declaration contradicts itself
     */
    public function layout(array $elements): void
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\DeclarationConstraints())->problem($elements, null);
        if ($problem === null) {
            return;
        }
        $errors = new \MySqlMemory\Session\Problem\Errors();
        $error = $errors->definition($problem, '') ?? $errors->expression($problem);
        \SqlSemantics\Diagnostic\Check::invariant($error !== null, 'Every fixed declaration condition has a SQL diagnostic.');
        throw $error;
    }

}
