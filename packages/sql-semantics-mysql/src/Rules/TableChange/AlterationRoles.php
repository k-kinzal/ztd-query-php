<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Platform\MySql\Statement\Alter as A;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDefaultSetting;

/**
 * Separates ordinary alterations from complete instructions checked on invocation.
 *
 * Validation without an eligible action, a creation-only transaction option,
 * and non-scalar defaults can occur in
 * unexecuted stored programs. Neither belongs to an ordinary ALTER value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AlterationRoles
{
    /**
     * @param list<A\AlterCommand> $commands Ordered alteration actions
     */
    public function deferred(array $commands): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->commands($commands)
            || (new \SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles())->commands($commands)
            || (new \SqlSemantics\Platform\MySql\Rules\Typing\Declaration\FixedSizeBounds())->problem($commands) !== null
            || $this->invalidValidation($commands)
            || $this->transaction($commands)
            || array_filter($commands, static fn (A\AlterCommand $command): bool => $command instanceof DeferredDefaultSetting) !== []
            || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacyExpressions())->commands($commands);
    }

    /**
     * Identifies the creation-only transaction option inside ALTER table-option runs.
     * The complete instruction may be stored, but an ordinary ALTER cannot use it.
     *
     * @param list<A\AlterCommand> $commands Ordered alteration actions
     */
    public function transaction(array $commands): bool
    {
        foreach ($commands as $command) {
            if ($command instanceof A\Command\SetTableOptions && array_filter($command->options, static fn ($option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Identifies a validation modifier without an action that accepts it.
     *
     * @param list<A\AlterCommand> $commands Ordered alteration actions
     */
    public function invalidValidation(array $commands): bool
    {
        $validated = false;
        $accepted = false;
        foreach ($commands as $command) {
            $validated = $validated || $command instanceof A\Modifier\ValidationOption;
            $accepted = $accepted || $command instanceof A\Column\AddColumn || $command instanceof A\Column\AddColumns
                || $command instanceof A\Column\ChangeColumn || $command instanceof A\Partition\ExchangePartition
                || $command instanceof A\Partition\TablespaceCommand || $command instanceof A\Partition\SecondaryLoad
                || $command instanceof A\Command\RenameElement && $command->kind === A\Command\ElementKind::Column
                || $command instanceof A\Partition\MaintainPartitions && $command->kind !== A\Partition\MaintenanceKind::Rebuild;
        }
        return $validated && !$accepted;
    }
}
