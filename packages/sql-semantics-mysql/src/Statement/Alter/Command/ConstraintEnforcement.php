<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * `ALTER CHECK c [NOT] ENFORCED` or `ALTER CONSTRAINT c [NOT] ENFORCED` (8.0.16 and later).
 *
 * Mirrors PT_alter_table_enforce_check_constraint and
 * PT_alter_table_enforce_constraint: CHECK names a check constraint,
 * CONSTRAINT a constraint of any kind (the server accepts the request only
 * for a check constraint).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-foreign-check-constraints.
 *
 * @visibility SqlSemantics
 */
final class ConstraintEnforcement implements AlterCommand
{
    use Snapshot;

    /**
     * @param ElementKind $kind CHECK or CONSTRAINT
     * @param Name $constraint The constraint name
     * @param bool $enforced Whether ENFORCED (true) or NOT ENFORCED (false) is written
     */
    public function __construct(public readonly ElementKind $kind, public readonly Name $constraint, public readonly bool $enforced)
    {
        Check::input($kind === ElementKind::Check || $kind === ElementKind::Constraint, 'Only CHECK or CONSTRAINT names an enforced constraint.');
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', $this->kind->value)->name($this->constraint, NameUse::Label);
        if (!$this->enforced) {
            $out->keyword('NOT');
        }
        $out->keyword('ENFORCED');
    }
}
