<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;

/**
 * Interprets the two native logging action forms.
 * @visibility SqlSemantics
 */
final class SequenceLoggingModeLowering
{
    /**
     * Resolves the native action into its closed assignment.
     *
     * @throws InvalidConstruction When the action is outside the logging form
     */
    public function lower(AlterCommand $command): SequenceLogging
    {
        Check::input($command instanceof TableAction, 'Sequence logging requires SET LOGGED or SET UNLOGGED.');
        return match ($command->kind) {
            TableActionKind::SetLogged => SequenceLogging::Logged,
            TableActionKind::SetUnlogged => SequenceLogging::Unlogged,
            TableActionKind::SetWithoutOids,
            TableActionKind::SetWithoutCluster,
            TableActionKind::NotOf,
            TableActionKind::EnableRowSecurity,
            TableActionKind::DisableRowSecurity,
            TableActionKind::ForceRowSecurity,
            TableActionKind::NoForceRowSecurity,
            TableActionKind::EnableAllTriggers,
            TableActionKind::EnableUserTriggers,
            TableActionKind::DisableAllTriggers,
            TableActionKind::DisableUserTriggers => throw new InvalidConstruction('Sequence logging requires SET LOGGED or SET UNLOGGED.'),
        };
    }
}
