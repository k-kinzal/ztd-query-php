<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A retained ALTER default whose operand has no ordinary scalar role.
 *
 * The owning instruction retains the target and every action. Its operand is
 * checked when the instruction reaches the target column, including in prepared
 * and stored statements. It is never substituted with a fabricated Scalar.
 *
 * @visibility public
 * @example Retaining a row default in a stored instruction
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $operation = $semantics->analyze('CREATE PROCEDURE p() ALTER TABLE t ALTER a SET DEFAULT (ROW(1,2))');
 *     $operation->statement->body->commands[0] instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDefaultSetting // => true
 */
final class DeferredDefaultSetting implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnName $column The target column
     * @param RowOperand|DeferredOperand $expression The complete default operand
     */
    public function __construct(public readonly ColumnName $column, public readonly RowOperand|DeferredOperand $expression, public readonly bool $columnWritten = true)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the target name and retained operand in the altered table's scope.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->column->deriveName($derivation, $scope->relations[0]->name ?? null);
        $derivation->scalar($this->expression, $scope);
    }

    /**
     * Writes the complete default action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        if ($this->columnWritten) {
            $out->keyword('COLUMN');
        }
        $out->node($this->column)->keyword('SET', 'DEFAULT')->symbol('(')->node($this->expression)->symbol(')');
    }
}
